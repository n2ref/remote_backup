# Remote backup
Backing up files and databases on remote servers

```
Backup remote or local files over SSH
Usage: php remote_backup.phar [OPTIONS]
Required arguments:
        -h      --host      Host name in config file
        -c      --config    Path to config file. Default conf.ini
Optional arguments:
        --help              Help info
        --verbose           Verbose info
Examples of usage:
php remote_backup.phar --host orange --config conf.ini --verbose
php remote_backup.phar --host orange
php remote_backup.phar --host all
```

### Config

Create your own set of files on your servers, which you need to periodically save in the configuration file.

```ini
[general]
;mail.email  =
; smtp, mail
;mail.method  = smtp
;mail.smtp.host   =
;mail.smtp.port   =
;mail.smtp.auth   = true
; SSL, TLS
;mail.smtp.secure = SSL
;mail.smtp.user   =
;mail.smtp.pass   =

[local]
dump.dir   = /mnt/backup/local/
dump.name  = %Y-%m-%d_%H
dump.count = 7

local.files.1 = /file/path/1
local.files.2 = /file/path/2
local.files.3 = /file/path/3
local.files.4 = /var/www

[server1]
; list active days, comma separator. Default - All
; mon, tue, wed, thu, fri, sat, sun
schedule.week_days = sat

dump.dir   = /mnt/backup/server1/
dump.name  = %Y-%m-%d_%H
dump.count = 7

local.mysql.on   = true
local.mysql.host = 192.168.1.2
local.mysql.port = 3306
local.mysql.user = root
local.mysql.pass = 
local.mysql.mysqldump_path = mysqldump
local.mysql.gzip_path      = gzip
; list backup databases, comma separator. Default - All
local.mysql.databases      =


remote.ssh.host = 192.168.1.5
remote.ssh.port = 22
remote.ssh.user = root
; none, pass, private_key, private_key-pass, agent
remote.ssh.auth_method  = pass
remote.ssh.pass         =
; file path: RCA
remote.ssh.private_key  =
remote.ssh.agent_socket =

remote.mysql.on   = true
remote.mysql.host = localhost
remote.mysql.port = 3306
remote.mysql.user = root
remote.mysql.pass =
remote.mysql.mysqldump_path = mysqldump
remote.mysql.gzip_path      = gzip
remote.mysql.tmp_dir        = /tmp
; list backup databases, comma separator. Default - All
remote.mysql.databases      =

remote.files.1 = /etc/crontab
remote.files.2 = /etc/nginx/sites-available
remote.files.3 = /var/www
```


To start, you can run the script manually or through cron.

`0 4 * * * root /opt/remote_backup.phar -h server1 -c /opt/conf.ini`



### Example of work

```
$ php7.4 /opt/remote_backup.phar -h server1 -c /opt/conf.ini --verbose
[16:31:47] BACKUP HOST: server1
[16:31:48] CREATE MYSQL DUMP
[16:31:49] DOWNLOAD MYSQL DUMP: /tmp/backup_2021-12-11_16.sql.gz ---> /mnt/backup/server1/2021-12-11_16/mysql_dump.sql.gz
[16:31:49] DELETE REMOTE MYSQL DUMP: /tmp/backup_2021-12-11_16.sql.gz
[16:31:49] COPY REMOTE: /etc/crontab ---> /mnt/backup/server1/2021-12-11_16/remote_files/etc/crontab
[16:31:49] COPY REMOTE: /etc/nginx/sites-available ---> /mnt/backup/server1/2021-12-11_16/remote_files/etc/nginx/sites-available
[16:31:49] COPY REMOTE: /var/www ---> /mnt/backup/server1/2021-12-11_16/remote_files/var/www
[16:33:47] CREATE ZIP
Done.
```