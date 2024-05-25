# Angle Audit Bundle
Audit Bundle for Symfony 5.4+ to produce automated reports for OS (Ubuntu) and DB (MySQL) privileged access

highly opinionated Angle deployment model, built on top of Symfony.

## Installation
Install composer dependency:

```
composer require anglemx/audit-bundle
```

Make sure that the Bundle has been enabled and auto-wired in the `bundles.php` file.

```php
// config/bundles.php

return [
    // ...
    Angle\AuditBundle\AngleAuditBundle::class => ['all' => true],
    // ...
];
```

## Usage
```bash
php bin/console angle:audit:____
```

Available commands:

```
angle:audit:application-updates
angle:audit:database-migrations
angle:audit:database-users
angle:audit:operating-system-access
angle:audit:operating-system-users
```

## Requirements
Standard Angle symfony application setup

MySQL Database
Ubuntu 18.04 - 22.04 Operating System
SwiftMailer


server-update.sh script should write its updates to `symfony-update.log` on the root dir

## TO-DO
- Ubuntu Utility
  - Check OS version compatibility
  - Pull SSH Authorized Keys file
  - Pull Auth.log file

# Mailer Configuration
Uses SwiftMailer (which is now deprecated in Symfony).

An .env variable called `MAILER_FROM` is required to be defined.

# Auth (`sshd`) Configurations
### Verify `sshd` log verbosity
Open the config file at `/etc/ssh/sshd_config`:

```
...

LogLevel VERBOSE

...
```

That is the default for Ubuntu 22.04, but verify that is the case.

### Configure `rsyslog` to log `sshd` to a special location
Create a file `60-sshd.conf` with the following line on it:

```
$template SshdFileFormat,"%TIMESTAMP:::date-rfc3339% %HOSTNAME% %syslogtag%%msg:::sp-if-no-1st-sp%%msg:::drop-last-lf%\n"
:programname, isequal, "sshd" /var/log/sshd.log;SshdFileFormat
```

Then copy it:

```
sudo cp sshd-rsyslog /etc/rsyslog.d/60-sshd.conf
```

Then test the configuration to make sure everything is OK:

```
rsyslogd -N1
```


Finally, restart the engine:

```
service rsyslog restart
```