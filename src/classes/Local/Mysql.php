<?php
namespace RemoteBackup\Local;


/**
 *
 */
class Mysql {

    private string $dir      = '';
    private string $name     = '';
    private bool   $verbose  = false;
    private array  $warnings = [];


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
     * @throws \Exception
     */
    public function startBackup(array $mysql): void {

        $backup_path = rtrim($this->dir, '/') . '/' . trim($this->name, '/');

        if ( ! is_dir($backup_path)) {
            mkdir($backup_path, 0755, true);
        }


        $mysqldump_path    = $mysql['mysqldump_path'] ?? 'mysqldump';
        $mysqldump_options = $mysql['mysqldump_options'] ?? '';
        $port              = $mysql['port'] ?? '';
        $pass              = $mysql['pass'] ?? '';
        $gzip_path         = $mysql['gzip_path'] ?? 'gzip';
        $databases         = $mysql['databases'] ?? [];


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

        $database_output = [];

        foreach ($databases as $database) {
            $dump_path = "{$backup_path}/mysql_{$database}.sql.gz";

            if ($this->verbose) {
                $time = date('H:i:s');
                echo "[{$time}] \e[92mCREATE MYSQL DUMP\e[0m {$dump_path}" . PHP_EOL;
            }

            $cmd = sprintf(
                " %s -u %s %s -h %s %s %s %s | %s > %s",
                $mysqldump_path, $mysql['user'], $pass, $mysql['host'], $port, $mysqldump_options, $database, $gzip_path, $dump_path
            );

            exec($cmd, $output);
            $database_output[$database] = $output;
        }


        if ( ! empty($database_output)) {
            foreach ($database_output as $database => $lines) {

                if ( ! empty($lines)) {
                    $database_warnings = [];

                    foreach ($lines as $line) {
                        if ($line != 'mysqldump: [Warning] Using a password on the command line interface can be insecure.') {
                            $database_warnings[] = $line;
                        }
                    }

                    if ( ! empty($database_warnings)) {
                        $this->warnings[] = "Backup database: {$database}";

                        foreach ($database_warnings as $database_warning) {
                            $this->warnings[] = $database_warning;
                        }
                    }
                }
            }
        }
    }


    /**
     * @return string|null
     */
    public function getWarnings():? string {

        return $this->warnings ? implode(PHP_EOL, $this->warnings) : null;
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