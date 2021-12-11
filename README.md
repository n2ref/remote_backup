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