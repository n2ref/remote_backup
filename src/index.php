<?php

if (PHP_SAPI === 'cli') {
    require_once 'classes/Backup.php';

    $options = getopt('h:c:', [
        'host:',
        'config:',
        'verbose',
        'help',
    ]);

    if ((isset($options['h']) || isset($options['host'])) &&
        ( ! isset($options['help']))
    ) {
        $host        = $options['host']   ?? $options['h'];
        $verbose     = isset($options['verbose']);
        $config_file = '';

        if ( ! empty($options['config'])) {
            $config_file = $options['config'];

        } elseif ( ! empty($options['c'])) {
            $config_file = $options['c'];
        }



        try {
            if ( ! function_exists('mb_substr')) {
                throw new \Exception("PHP extension 'mbstring' not found");
            }
            if ( ! class_exists('ZipArchive')) {
                throw new \Exception("PHP extension 'zip' not found");
            }
            if ( ! class_exists('DOMDocument')) {
                throw new \Exception("PHP extension 'xml' not found");
            }

            if (empty($config_file)) {
                $config_file = 'conf.ini';
            }
            if (mb_substr($config_file, 0, 1) !== '/') {
                $config_file = getcwd() . "/{$config_file}";
            }


            if ( ! is_file($config_file)) {
                throw new \Exception("Config file not found '{$config_file}'. Set -c key or --help");
            }

            if (empty($host)) {
                throw new \Exception("Incorrect parameter host: Empty");
            }

            if ($host == 'general') {
                throw new \Exception("Incorrect parameter host: General is a reserved word");
            }

            $config_file = realpath($config_file);

            $backup = new \RemoteBackup\Backup($config_file, $verbose);
            $backup->dispatcher($host);


            if ($verbose) {
                $time = date('H:i:s');
                echo "[{$time}] \e[92mDone.\e[0m" . PHP_EOL;
            }

        } catch (Exception $e) {
            $time = date('H:i:s');
            echo "[{$time}] \e[91mERROR: " . $e->getMessage() . "\e[0m" . PHP_EOL;
        }

    } else {
        echo implode(PHP_EOL, [
            "Backup remote or local files over SSH",
            'Usage: php remote_backup.phar [OPTIONS]',
            'Required arguments:',
            "\t-h\t--host\t\tHost name in config file",
            "\t-c\t--config\tPath to config file. Default conf.ini",
            'Optional arguments:',
            "\t--help\t\tHelp info",
            "\t--verbose\tVerbose info",
            "Examples of usage:",
            "php remote_backup.phar --host orange --config conf.ini --verbose",
            "php remote_backup.phar --host orange",
            "php remote_backup.phar --host all",
        ]) . PHP_EOL;
    }

} else {
    echo "\e[91mCli only\e[0m";
}