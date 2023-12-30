<?php
namespace RemoteBackup\Local;


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
     * @param array $mysql
     * @return void
     */
    public function startBackup(array $mysql) {

        $backup_path = rtrim($this->dir, '/') . '/' . trim($this->name, '/');

        if ( ! is_dir($backup_path)) {
            mkdir($backup_path, 0755, true);
        }


        $mysqldump_path = $mysql['mysqldump_path'] ?? 'mysqldump';
        $port           = $mysql['port'] ?? '';
        $pass           = $mysql['pass'] ?? '';
        $gzip_path      = $mysql['gzip_path'] ?? 'gzip';
        $databases      = $mysql['databases'] ?? [];


        if ( ! empty($databases)) {
            $databases = explode(",", $databases);
            $databases = array_map('trim', $databases);
        } else {
            $databases = $this->getDatabases($mysql);
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

        foreach ($databases as $database) {
            $dump_path = "{$backup_path}/mysql_{$database}.sql.gz";

            if ($this->verbose) {
                $time = date('H:i:s');
                echo "[{$time}] \e[92mCREATE MYSQL DUMP\e[0m {$dump_path}" . PHP_EOL;
            }

            $cmd = sprintf(
                " %s -u %s %s -h %s %s %s | %s > %s",
                $mysqldump_path, $mysql['user'], $pass, $mysql['host'], $port, $database, $gzip_path, $dump_path
            );

            exec($cmd);
        }
    }


    /**
     * Получение всех доступных названий баз данных
     * @return array
     */
    private function getDatabases(array $mysql) {

        $user = ! empty($mysql['user']) ? $mysql['user'] : null;
        $pass = ! empty($mysql['pass']) ? $mysql['pass'] : null;
        $host = ! empty($mysql['host']) ? $mysql['host'] : 'localhost';

        if ($this->verbose) {
            $time = date('H:i:s');
            echo "[{$time}] \e[92mSHOW MYSQL DATABASES\e[0m " . PHP_EOL;
        }

        $dbh = new \PDO( "mysql:host={$host}", $user, $pass);
        $dbs = $dbh->query('SHOW DATABASES');

        return $dbs->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
}