<?php
namespace RemoteBackup\Remote;

use phpseclib\Net\SFTP;
use RemoteBackup\Tools;


/**
 *
 */
class Files {

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
     * Бекап файлов
     * @param SFTP  $sftp
     * @param array $files
     * @return bool
     * @throws \Exception
     */
    public function startBackup(SFTP $sftp, array $files): bool {

        if (empty($files)) {
            return false;
        }

        $backup_path       = rtrim($this->dir, '/') . '/' . trim($this->name, '/');
        $backup_path_files = "{$backup_path}/remote_files";

        if ( ! is_dir($backup_path_files)) {
            mkdir($backup_path_files, 0755, true);
        }


        if ( ! is_dir($backup_path_files))  {
            throw new \Exception("Failed to create directory: {$backup_path_files}");
        }

        if ( ! is_writable($backup_path_files)) {
            throw new \Exception("Dir not writable: {$backup_path_files}");
        }


        register_shutdown_function(function () use ($backup_path_files) {
            if (is_dir($backup_path_files)) {
                if ($this->verbose) {
                    $time = date('H:i:s');
                    echo "[{$time}] \e[92mREMOVE TMP DIR:\e[0m {$backup_path_files}\e[0m" . PHP_EOL;
                }
                Tools::removeDir($backup_path_files);
            }
        });


        foreach ($files as $file_path) {

            if (substr($file_path, 0, 1) === '/') {
                $base_dir = dirname($file_path);

                if ( ! is_dir("{$backup_path_files}{$base_dir}")) {
                    mkdir("{$backup_path_files}{$base_dir}", 0755, true);
                }

                if ($this->verbose) {
                    $time = date('H:i:s');
                    echo "[{$time}] \e[92mCOPY REMOTE:\e[0m {$file_path} ---> {$backup_path_files}{$file_path}\e[0m" . PHP_EOL;
                }

                if ($sftp->is_dir($file_path)) {
                  $this->copyDir($sftp, $file_path, "{$backup_path_files}{$file_path}");

                } elseif ($sftp->is_file($file_path)) {
                    $sftp->get(
                        $file_path,
                        "{$backup_path_files}/{$file_path}"
                    );

                } else {
                    $time    = date('H:i:s');
                    $message = "File not found: {$file_path}";
                    echo "[{$time}] \e[93m{$message}\e[0m" . PHP_EOL;

                    $this->warnings[] = $message;
                }


            } else {
                $time    = date('H:i:s');
                $message = "File skipped, address must start with /. {$file_path}";
                echo "[{$time}] \e[93m{$message}\e[0m" . PHP_EOL;

                $this->warnings[] = $message;
            }
        }

        if ($this->verbose) {
            $time = date('H:i:s');
            echo "[{$time}] \e[92mCREATE ZIP\e[0m" . PHP_EOL;
        }

        Tools::zipDir($backup_path_files, "{$backup_path}/files.zip");


        if ($this->verbose) {
            $time = date('H:i:s');
            echo "[{$time}] \e[92mREMOVE TMP DIR:\e[0m {$backup_path_files}\e[0m" . PHP_EOL;
        }
        Tools::removeDir($backup_path_files);

        return true;
    }


    /**
     * @return string|null
     */
    public function getWarnings():? string {

        return $this->warnings ? implode(PHP_EOL, $this->warnings) : null;
    }


    /**
     * Копирование директории вместе с ее содержимым
     * @param SFTP   $sftp
     * @param string $dir
     * @param string $destination
     * @return void
     */
    private function copyDir(SFTP $sftp, string $dir, string $destination) {

        $files = $sftp->nlist($dir);

        if ( ! empty($files)) {
            foreach ($files as $file) {

                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                if ($sftp->is_dir("{$dir}/{$file}")) {
                    $this->copyDir($sftp, "{$dir}/{$file}", "{$destination}/{$file}");

                } elseif ($sftp->is_file("{$dir}/{$file}")) {
                    if ( ! is_dir("{$destination}")) {
                        mkdir("{$destination}", 0755, true);
                    }

                    $sftp->get(
                        "{$dir}/{$file}",
                        "{$destination}/{$file}"
                    );
                }
            }
        }
    }
}