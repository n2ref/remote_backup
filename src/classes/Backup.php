<?php
namespace RemoteBackup;

use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use phpseclib\System\SSH\Agent;


require_once __DIR__ . '/../vendor/autoload.php';
require_once 'Tools.php';
require_once 'Local/Files.php';
require_once 'Remote/Files.php';
require_once 'Remote/Mysql.php';


/**
 *
 */
class Backup {

    /**
     * @var string
     */
    private $config_file = '';

    /**
     * @var bool
     */
    private $verbose = false;


    /**
     * @param string $config_file
     * @param bool   $verbose
     */
    public function __construct(string $config_file, bool $verbose = false) {

        $this->config_file = $config_file;
        $this->verbose     = $verbose;
    }


    /**
     * Диспетчер
     * @param string $host
     * @throws \Exception
     */
    public function dispatcher(string $host) {

        $config = Tools::getConfig($this->config_file, 'general');

        try {
            if ($host == 'all') {
                $config_hosts = Tools::getConfig($this->config_file);

                if ( ! empty($config_hosts)) {
                    $errors = [];
                    foreach ($config_hosts as $host_name => $config_host) {
                        try {
                            if ( ! in_array($host_name, ['general'])) {
                                $this->backupHost($config_host, $host_name);
                            }

                        } catch (\Exception $e) {
                            $errors[] = "{$host_name} - " . $e->getMessage();
                        }
                    }

                    if ( ! empty($errors)) {
                        throw new \Exception("Errors: " . implode(', ', $errors));
                    }

                } else {
                    throw new \Exception('Not found hosts in config');
                }


            } else {
                $config_host = Tools::getConfig($this->config_file, $host);
                $this->backupHost($config_host, $host);
            }

        } catch (\Exception $e) {
            if ( ! empty($config['mail']) &&
                 ! empty($config['mail']['email']) &&
                 ! empty($config['mail']['smtp'])
            ) {
                $is_send = Tools::sendMail(
                    $config['mail']['email'],
                    'Error backup',
                    $e->getMessage(),
                    $config['mail']
                );

                if ( ! $is_send) {
                    $time = date('H:i:s');
                    echo "[{$time}] \e[91mError send email" . PHP_EOL;
                }
            }

            throw $e;
        }
    }


    /**
     * Бэкап хоста
     * @param array  $config_host
     * @param string $host
     * @return void
     * @throws \Exception
     */
    private function backupHost(array $config_host, string $host) {

        if ($this->verbose) {
            $time = date('H:i:s');
            echo "[{$time}] \e[92mBACKUP HOST:\e[0m {$host}" . PHP_EOL;
        }

        if (empty($config_host)) {
            throw new \Exception("Incorrect parameter host: Empty '{$host}' in config file");
        }

        if (empty($config_host['dump'])) {
            throw new \Exception("Empty dump param in config file, section [{$host}]");
        }
        if (empty($config_host['dump']['dir'])) {
            throw new \Exception("Empty dump.dir param in config file, section [{$host}]");
        }
        if (empty($config_host['dump']['name'])) {
            throw new \Exception("Empty dump.name param in config file, section [{$host}]");
        }
        if ( ! isset($config_host['dump']['count'])) {
            throw new \Exception("Empty dump.count param in config file, section [{$host}]");
        }

        $config_host['dump']['dir']  = rtrim($config_host['dump']['dir'], '/');
        $config_host['dump']['name'] = str_replace([
            '%Y', '%m', '%d', '%H', '%i', '%s',
        ], [
            date('Y'), date('m'), date('d'), date('H'), date('i'), date('s'),
        ], $config_host['dump']['name']);


        if ( ! empty($config_host['local'])) {
            if ( ! empty($config_host['local']['files'])) {
                $files = new Local\Files(
                    $config_host['dump']['dir'],
                    $config_host['dump']['name'],
                    $this->verbose
                );
                $files->startBackup($config_host['local']['files']);
            }
        }


        if ( ! empty($config_host['remote'])) {
            if (empty($config_host['remote']['ssh'])) {
                throw new \Exception("Empty remote.ssh option");
            }
            if (empty($config_host['remote']['ssh']['host'])) {
                throw new \Exception("Empty remote.ssh.host option");
            }
            if (empty($config_host['remote']['ssh']['user'])) {
                throw new \Exception("Empty remote.ssh.user option");
            }
            if (empty($config_host['remote']['ssh']['auth_method'])) {
                throw new \Exception("Empty remote.ssh.auth_method option");
            }

            // FIXME error connect phpseclib. SSH server: ssh_dispatch_run_fatal: Connection from  port : message authentication code incorrect "[preauth]"
            // https://central.owncloud.org/t/sftp-external-storage-access-runtimeexception-invalid-size/31845/3
            for ($i = 0; $i < 20; $i++) {
                try {
                    $sftp = $this->connectSFTP($config_host['remote']['ssh']);
                    continue;

                } catch (\Exception $e) {
                    if ($e->getMessage() != 'Connection closed by server') {
                        throw $e;
                    }
                }
            }


            if ( ! empty($config_host['remote']['files'])) {
                $files = new Remote\Files(
                    $config_host['dump']['dir'],
                    $config_host['dump']['name'],
                    $this->verbose
                );

                $files->startBackup(
                    $sftp,
                    $config_host['remote']['files']
                );
            }


            if ( ! empty($config_host['remote']['mysql']) &&
                isset($config_host['remote']['mysql']['on']) &&
                $config_host['remote']['mysql']['on']
            ) {
                $mysql = new Remote\Mysql(
                    $config_host['dump']['dir'],
                    $config_host['dump']['name'],
                    $this->verbose
                );
                $mysql->startBackup(
                    $sftp,
                    $config_host['remote']['mysql']
                );
            }
        }



        $dirs = Tools::fetchDirs($config_host['dump']['dir']);

        if ( ! empty($dirs) &&
             ! empty($config_host['dump']['count']) &&
            $config_host['dump']['count'] > 0
        ) {
            $count_remove = 1;
            foreach ($dirs as $dir) {
                if ($config_host['dump']['count'] < $count_remove++) {

                    if ($this->verbose) {
                        $time = date('H:i:s');
                        echo "[{$time}] \e[92mREMOVE OLD DUMP: {$dir}" . PHP_EOL;
                    }

                    Tools::removeDir($dir);
                }
            }
        }
    }


    /**
     * Подключение к SSH
     * @param array $conf_ssh
     * @return SFTP
     * @throws \Exception
     */
    private function connectSFTP(array $conf_ssh): SFTP {

        $port = $conf_ssh['port'] ?? 22;
        $sftp = new SFTP($conf_ssh['host'], $port, 3600);
        switch ($conf_ssh['auth_method']) {
            case 'pass':
                if (empty($conf_ssh['pass'])) {
                    throw new \Exception("Empty remote.ssh.pass option");
                }
                $sftp->login($conf_ssh['user'], $conf_ssh['pass']);
                break;

            case 'private_key':
                if (empty($conf_ssh['private_key'])) {
                    throw new \Exception("Empty remote.ssh.private_key option");
                }
                if ( ! file_exists($conf_ssh['private_key'])) {
                    throw new \Exception("File not found \"{$conf_ssh['private_key']}\"");
                }
                // VERSION 3
                // $pub_key = PublicKeyLoader::load(file_get_contents($conf_ssh['private_key']));
                $pub_key = new RSA();
                $pub_key->loadKey(file_get_contents($conf_ssh['private_key']));
                $sftp->login($conf_ssh['user'], $pub_key);
                break;

            case 'private_key-pass':
                if (empty($conf_ssh['private_key'])) {
                    throw new \Exception("Empty remote.ssh.pub_key option");
                }
                if ( ! file_exists($conf_ssh['private_key'])) {
                    throw new \Exception("File not found \"{$conf_ssh['private_key']}\"");
                }
                if (empty($conf_ssh['pass'])) {
                    throw new \Exception("Empty remote.ssh.pass option");
                }
                // VERSION 3
                // $pub_key = PublicKeyLoader::load(file_get_contents($conf_ssh['private_key']), $conf_ssh['pass']);
                $pub_key = new RSA();
                $pub_key->setPassword($conf_ssh['pass']);
                $pub_key->loadKey(file_get_contents($conf_ssh['private_key']));
                $sftp->login($conf_ssh['user'], $pub_key);
                break;

            case 'agent':
                $agent_socket = $conf_ssh['agent_socket'] ?? null;
                $sftp->login($conf_ssh['user'], new Agent($agent_socket));
                break;

            case '':
            case 'none':
                $sftp->login($conf_ssh['user']);
                break;

            default:
                throw new \Exception("Unknown ssh auth method: {$conf_ssh['auth_method']}");
        }

        return $sftp;
    }
}