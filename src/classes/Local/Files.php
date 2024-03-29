<?php
namespace RemoteBackup\Local;


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
     * @param array $files
     * @return void
     * @throws \Exception
     */
    public function startBackup(array $files): void {

        if ( ! empty($files)) {
            $backup_path       = rtrim($this->dir, '/') . '/' . trim($this->name, '/');
            $backup_path_files = "{$backup_path}/local_files";


            if ( ! is_dir($backup_path_files)) {
                mkdir($backup_path_files, 0755, true);
            }


            if (is_dir($backup_path_files)) {
                if ( ! is_writable($backup_path_files)) {
                    throw new \Exception("Dir not writable: {$backup_path_files}");
                }

                register_shutdown_function(function() use ($backup_path_files) {
                    if (is_dir($backup_path_files)) {
                        if ($this->verbose) {
                            $time = date('H:i:s');
                            echo "[{$time}] \e[92mREMOVE TMP DIR:\e[0m {$backup_path_files}" . PHP_EOL;
                        }
                        Tools::removeDir($backup_path_files);
                    }
                });


                foreach ($files as $file_path) {
                    if (substr($file_path, 0, 1) !== '/') {
                        $time    = date('H:i:s');
                        $message = "File skipped, address must start with /. {$file_path}";
                        echo "[{$time}] \e[93m{$message} \e[93m\e[0m" . PHP_EOL;

                        $this->warnings[] = $message;
                        continue;
                    }

                    $base_dir = dirname($file_path);

                    if ( ! is_dir("{$backup_path_files}{$base_dir}")) {
                        mkdir("{$backup_path_files}{$base_dir}", 0755, true);
                    }

                    if ( ! is_file($file_path) && ! is_dir($file_path)) {
                        $time    = date('H:i:s');
                        $message = "File not found: {$file_path}";
                        echo "[{$time}] \e[93m{$message} \e[93m\e[0m" . PHP_EOL;

                        $this->warnings[] = $message;
                        continue;
                    }


                    if ($this->verbose) {
                        $time = date('H:i:s');
                        echo "[{$time}] \e[92mCOPY LOCAL:\e[0m {$file_path} ---> {$backup_path_files}{$file_path}" . PHP_EOL;
                    }

                    $this->copyr($file_path, "{$backup_path_files}{$file_path}");
                }


                if ($this->verbose) {
                    $time = date('H:i:s');
                    echo "[{$time}] \e[92mCREATE ZIP\e[0m" . PHP_EOL;
                }

                Tools::zipDir($backup_path_files, "{$backup_path}/files.zip");

                if ($this->verbose) {
                    $time = date('H:i:s');
                    echo "[{$time}] \e[92mREMOVE TMP DIR:\e[0m {$backup_path_files}" . PHP_EOL;
                }

                Tools::removeDir($backup_path_files);

            } else {
                throw new \Exception("Failed to create directory: {$backup_path_files}");
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
     * Copy a file, or recursively copy a folder and its contents
     * @param string $source
     * @param string $dest
     * @return bool
     */
    private function copyr(string $source, string $dest): bool {

        // Simple copy for a file
        if (is_file($source)) {
            return copy($source, $dest);
        }

        // Make destination directory
        if ( ! is_dir($dest)) {
            mkdir($dest, 755, true);
        }

        // Loop through the folder
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Deep copy directories
            $this->copyr(
                $source . DIRECTORY_SEPARATOR . $entry,
                $dest . DIRECTORY_SEPARATOR . $entry
            );
        }

        $dir->close();
        return true;
    }
}