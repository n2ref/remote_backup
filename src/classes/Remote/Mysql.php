<?php
namespace RemoteBackup\Remote;


use phpseclib\Net\SFTP;


/**
 *
 */
class Mysql {

    private $dir     = '';
    private $name    = '';
    private $verbose = false;


    /**
     * @param string $dir
     * @param string $name
     * @param bool $verbose
     * @throws \Exception
     */
    public function __construct(string $dir, string $name, bool $verbose = false) {

        if (empty($dir)) {
            throw new \Exception("Empty dir option");
        }
        if (empty($name)) {
            throw new \Exception("Empty dir option");
        }

        $this->dir     = $dir;
        $this->name    = $name;
        $this->verbose = $verbose;
    }


    /**
     * @param SFTP  $sftp
     * @param array $mysql
     * @return void
     */
    public function startBackup(SFTP $sftp, array $mysql) {


        $backup_path = rtrim($this->dir, '/') . '/' . trim($this->name, '/');

        if ( ! is_dir($backup_path)) {
            mkdir($backup_path, 0755, true);
        }


        $mysqldump_path = $mysql['mysqldump_path'] ?? 'mysqldump';
        $port           = $mysql['port'] ?? '';
        $pass           = $mysql['pass'] ?? '';
        $gzip_path      = $mysql['gzip_path'] ?? 'gzip';
        $tmp_dir        = rtrim($mysql['tmp_dir'] ?? '/tmp', '/');
        $databases      = $mysql['databases'] ?? [];


        if ( ! empty($databases)) {
            $databases = explode(",", $databases);
            $databases = array_map('trim', $databases);
            $databases = implode(' ', $databases);
        } else {
            $databases = ' -A ';
        }

        if ( ! empty($port)) {
            $port = " -P {$port} ";
        } else {
            $port = '';
        }

        if ( ! empty($pass)) {
            $pass = " -p'{$pass}' ";
        } else {
            $pass = '';
        }

        if ($this->verbose) {
            $time = date('H:i:s');
            echo "[{$time}] \e[92mCREATE MYSQL DUMP\e[0m" . PHP_EOL;
        }

        $sftp->exec(sprintf(
            " %s -u %s %s -h %s %s %s | %s > %s/backup_%s.sql.gz\n",
            $mysqldump_path, $mysql['user'], $pass, $mysql['host'], $port, $databases, $gzip_path, $tmp_dir, $this->name,
        ));

        if ($this->verbose) {
            $time = date('H:i:s');
            echo "[{$time}] \e[92mDOWNLOAD MYSQL DUMP:\e[0m {$tmp_dir}/backup_{$this->name}.sql.gz ---> {$this->dir}/{$this->name}/mysql_dump.sql.gz" . PHP_EOL;
        }

        $sftp->get(
            "{$tmp_dir}/backup_{$this->name}.sql.gz",
            "{$this->dir}/{$this->name}/mysql_dump.sql.gz"
        );


        if ($this->verbose) {
            $time = date('H:i:s');
            echo "[{$time}] \e[92mDELETE REMOTE MYSQL DUMP:\e[0m {$tmp_dir}/backup_{$this->name}.sql.gz" . PHP_EOL;
        }

        $sftp->delete("{$tmp_dir}/backup_{$this->name}.sql.gz");
    }
}