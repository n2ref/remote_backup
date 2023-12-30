<?php
namespace RemoteBackup;

/**
 * Class Tools
 */
class Tools {


    /**
     * Parses INI file adding extends functionality via ":base" postfix on namespace.
     *
     * @param  string $filename
     * @param  string $section
     * @return array
     * @throws \Exception
     */
    public static function getConfig(string $filename, string $section = null): array {

        $p_ini  = parse_ini_file($filename, true);
        $config = [];

        foreach ($p_ini as $namespace => $properties) {
            if (is_array($properties)) {
                @list($name, $extends) = explode(':', $namespace);
                $name    = trim($name);
                $extends = trim($extends);
                // create namespace if necessary
                if ( ! isset($config[$name])) $config[$name] = [];
                // inherit base namespace
                if (isset($p_ini[$extends])) {
                    foreach ($p_ini[$extends] as $key => $val) $config[$name] = self::processKey($config[$name], $key, $val);;
                }
                // overwrite / set current namespace values
                foreach ($properties as $key => $val) $config[$name] = self::processKey($config[$name], $key, $val);
            } else {
                if ( ! isset($config['global'])) {
                    $config['global'] = [];
                }
                $parsed_key       = self::processKey([], $namespace, $properties);
                $config['global'] = self::array_merge_recursive_distinct($config['global'], $parsed_key);
            }
        }
        if ($section) {
            if (isset($config[$section])) {
                return $config[$section] ?: [];
            } else {
                throw new \Exception("Config section '{$section}' not found");
            }
        } else {
            if (count($config) === 1 && isset($config['global'])) {
                return $config['global'] ?: [];
            }

            return $config;
        }
    }


    /**
     * Получение списка папок
     * @param string $dir
     * @return array
     */
    public static function fetchDirs(string $dir): array {

        $dirs = [];

        $c_dir = scandir($dir);
        foreach ($c_dir as $value) {

            if ( ! in_array($value, [".", ".."])) {
                $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($value, DIRECTORY_SEPARATOR);

                if (is_dir($path)) {
                    $dirs[] = $path;
                }
            }
        }

        if ( ! empty($dirs)) {
            rsort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $dirs;
    }


    /**
     * Рекурсивное удаление папки
     * @param $dir
     */
    public static function removeDir($dir) {

        $it    = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }


    /**
     * @param $dir_path
     * @param $file_path
     * @return void
     * @throws \Exception
     */
    public static function zipDir($dir_path, $file_path) {

        $zip = new \ZipArchive();
        if ( ! $zip->open($file_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new \Exception("Failed to create archive: {$file_path}");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir_path),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if (in_array(substr($file, strrpos($file, '/') + 1), ['.', '..'])) continue;

            $file = realpath($file);

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($dir_path . '/', '', $file . '/'));

            } elseif (is_file($file) === true) {
                $zip->addFromString(str_replace($dir_path . '/', '', $file), file_get_contents($file));
            }
        }

        $zip->close();
    }


    /**
     * Отправка письма
     * @param string $to Email поучателя. Могут содержать несколько адресов разделенных зяпятой.
     * @param string $subject Тема письма
     * @param string $message Тело письма
     * @param array $options
     *      Опциональные значения для письма.
     *      Может содержать такие ключи как
     *      charset - Кодировка сообщения. По умолчанию содержет - utf-8
     *      content_type - Тип сожержимого. По умолчанию содержет - text/html
     *      from - Адрес отправителя. По умолчанию содержет - noreply@localhost
     *      cc - Адреса вторичных получателей письма, к которым направляется копия. По умолчанию содержет - false
     *      bcc - Адреса получателей письма, чьи адреса не следует показывать другим получателям. По умолчанию содержет - false
     *      method - Метод отправки. Может принимать значения smtp и mail. По умолчанию содержет - mail
     *      smtp.host - Хост для smtp отправки. По умолчанию содержет - localhost
     *      smtp.port - Порт для smtp отправки. По умолчанию содержет - 25
     *      smtp.auth - Признак аутентификации для smtp отправки. По умолчанию содержет - false
     *      smtp.secure - Название шифрования, TLS или SSL. По умолчанию без шифрования
     *      smtp.user - Пользователь при использовании аутентификации для smtp отправки. По умолчанию содержет пустую строку
     *      smtp.pass - Пароль при использовании аутентификации для smtp отправки. По умолчанию содержет пустую строку
     *      smtp.timeout - Таймаут для smtp отправки. По умолчанию содержет - 15
     * @return bool Успешна либо нет отправка сообщения
     * @throws \Exception Исключение с текстом произошедшей ошибки
     */
    public static function sendMail($to, $subject, $message, array $options = array()) {

        $options['charset']      = isset($options['charset']) && trim($options['charset']) != '' ? $options['charset'] : 'utf-8';
        $options['content_type'] = isset($options['content_type']) && trim($options['content_type']) != '' ? $options['content_type'] : 'text/html';
        $options['server_name']  = isset($options['server_name']) && trim($options['server_name']) != '' ? $options['server_name'] : 'localhost';
        $options['from']         = isset($options['from']) && trim($options['from']) != '' ? $options['from'] : 'noreply@' . $options['server_name'];
        $options['cc']           = isset($options['cc']) && trim($options['cc']) != '' ? $options['cc'] : false;
        $options['bcc']          = isset($options['bcc']) && trim($options['bcc']) != '' ? $options['bcc'] : false;
        $subject                 = $subject != null && trim($subject) != '' ? $subject : '(No Subject)';


        $headers = array(
            "MIME-Version: 1.0",
            "Content-type: {$options['content_type']}; charset={$options['charset']}",
            "From: {$options['from']}",
            "Content-Transfer-Encoding: base64",
            "X-Mailer: PHP/" . phpversion()
        );

        if ($options['cc']) $headers[] = $options['cc'];
        if ($options['bcc']) $headers[] = $options['bcc'];


        if (isset($options['method']) && strtoupper($options['method']) == 'SMTP') {
            $options['smtp']['host']    = isset($options['smtp']['host']) && trim($options['smtp']['host']) != '' ? $options['smtp']['host'] : $options['server_name'];
            $options['smtp']['port']    = isset($options['smtp']['port']) && (int)($options['smtp']['port']) > 0  ? $options['smtp']['port'] : 25;
            $options['smtp']['secure']  = isset($options['smtp']['secure']) ? $options['smtp']['secure'] : '';
            $options['smtp']['auth']    = isset($options['smtp']['auth']) ? (bool)$options['smtp']['auth'] : false;
            $options['smtp']['user']    = isset($options['smtp']['user']) ? $options['smtp']['user'] : '';
            $options['smtp']['pass']    = isset($options['smtp']['pass']) ? $options['smtp']['pass'] : '';
            $options['smtp']['timeout'] = isset($options['smtp']['timeout']) && (int)($options['smtp']['timeout']) > 0 ? $options['smtp']['timeout'] : 15;

            $headers[] = "Subject: {$subject}";
            $headers[] = "To: <" . implode('>, <', explode(',', $to)) . ">";
            $headers[] = "\r\n";
            $headers[] = wordwrap(base64_encode($message), 75, "\n", true);
            $headers[] = "\r\n";

            $recipients = explode(',', $to);
            $errno      = '';
            $errstr     = '';


            if (strtoupper($options['smtp']['secure']) == 'SSL') {
                $options['smtp']['host'] = 'ssl://' . preg_replace('~^([a-zA-Z0-9]+:|)//~', '', $options['smtp']['host']);
            }


            if ( ! ($socket = fsockopen($options['smtp']['host'], $options['smtp']['port'], $errno, $errstr, $options['smtp']['timeout']))) {
                throw new \Exception("Error connecting to '{$options['smtp']['host']}': {$errno} - {$errstr}");
            }

            if (substr(PHP_OS, 0, 3) != "WIN") socket_set_timeout($socket, $options['smtp']['timeout'], 0);

            self::serverParse($socket, '220');

            fwrite($socket, 'EHLO ' . $options['smtp']['host'] . "\r\n");
            self::serverParse($socket, '250');

            if (strtoupper($options['smtp']['secure']) == 'TLS') {
                fwrite($socket, 'STARTTLS' . "\r\n");
                self::serverParse($socket, '250');
            }


            if ($options['smtp']['auth']) {
                fwrite($socket, 'AUTH LOGIN' . "\r\n");
                self::serverParse($socket, '334');

                fwrite($socket, base64_encode($options['smtp']['user']) . "\r\n");
                self::serverParse($socket, '334');

                fwrite($socket, base64_encode($options['smtp']['pass']) . "\r\n");
                self::serverParse($socket, '235');
            }

            fwrite($socket, "MAIL FROM: <{$options['from']}>\r\n");
            self::serverParse($socket, '250');


            foreach ($recipients as $email) {
                fwrite($socket, 'RCPT TO: <' . $email . '>' . "\r\n");
                self::serverParse($socket, '250');
            }

            fwrite($socket, 'DATA' . "\r\n");
            self::serverParse($socket, '354');

            fwrite($socket, implode("\r\n", $headers));
            fwrite($socket, '.' . "\r\n");
            self::serverParse($socket, '250');

            fwrite($socket, 'QUIT' . "\r\n");
            fclose($socket);

            return true;

        } else {

            return mail($to, $subject, wordwrap(base64_encode($message), 75, "\n", true), implode("\r\n", $headers));
        }
    }


    /**
     * Получение ответа от сервера
     * @param resource $socket
     * @param string $expected_response
     * @throws \Exception
     */
    private static function serverParse($socket, $expected_response) {

        $server_response = '';
        while (substr($server_response, 3, 1) != ' ') {
            if ( ! ($server_response = fgets($socket, 256)))  {
                throw new \Exception('Error while fetching server response codes.');
            }
        }
        if (substr($server_response, 0, 3) != $expected_response) {
            throw new \Exception("Unable to send e-mail: {$server_response}");
        }
    }


    /**
     * array_merge_recursive does indeed merge arrays, but it converts values with duplicate
     * keys to arrays rather than overwriting the value in the first array with the duplicate
     * value in the second array, as array_merge does. I.e., with array_merge_recursive,
     * this happens (documented behavior):
     *
     * array_merge_recursive(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('org value', 'new value'));
     *
     * array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
     * Matching keys' values in the second array overwrite those in the first array, as is the
     * case with array_merge, i.e.:
     *
     * array_merge_recursive_distinct(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('new value'));
     *
     * Parameters are passed by reference, though only for performance reasons. They're not
     * altered by this function.
     *
     * @param array $array1
     * @param array $array2
     * @return array
     * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
     * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
     */
    private static function array_merge_recursive_distinct (array &$array1, array &$array2) {
        $merged = $array1;

        foreach ( $array2 as $key => &$value ) {
            if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) ) {
                $merged [$key] = self::array_merge_recursive_distinct ( $merged [$key], $value );
            } else {
                $merged [$key] = $value;
            }
        }

        return $merged;
    }


    /**
     * Процесс разделения на субсекции ключей конфига
     * @param array $config
     * @param string $key
     * @param string $val
     * @return array
     */
    private static function processKey($config, $key, $val) {
        $nest_separator = '.';
        if (strpos($key, $nest_separator) !== false) {
            $pieces = explode($nest_separator, $key, 2);
            if (strlen($pieces[0]) && strlen($pieces[1])) {
                if ( ! isset($config[$pieces[0]])) {
                    if ($pieces[0] === '0' && ! empty($config)) {
                        // convert the current values in $config into an array
                        $config = array($pieces[0] => $config);
                    } else {
                        $config[$pieces[0]] = array();
                    }
                }
                $config[$pieces[0]] = self::processKey($config[$pieces[0]], $pieces[1], $val);
            }
        } else {
            $config[$key] = $val;
        }
        return $config;
    }
}