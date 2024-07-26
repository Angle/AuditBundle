# Angle Audit Bundle
Audit Bundle for Symfony 5.4+ to produce automated reports for OS (Ubuntu) and DB (MySQL) privileged access

Highly opinionated Angle deployment model, built on top of Symfony.

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
php bin/console angle:audit:report-email
```

Available report commands:

```
angle:audit:application-updates
angle:audit:database-migrations
angle:audit:database-users
angle:audit:operating-system-access
angle:audit:operating-system-users
```

## Requirements
Standard Angle symfony application setup

- MySQL Database
- Ubuntu 18.04 - 22.04 Operating System
- SwiftMailer

Application is installed in `/var/www/`


## Configuration
### Server-update.sh

This will write to a file called `symfony-update.log` at the root of the project, where `server-update.sh` is run.

Verify that the following lines are included in your `server-update.sh` script:

Before the server update runs:
```bash
echo "$(date '+%Y-%m-%d %H:%M:%S') $(whoami) - started server-update..." >> ./symfony-update.log
echo "$(date '+%Y-%m-%d %H:%M:%S') $(whoami) - current git branch: $(git rev-parse --abbrev-ref HEAD 2>&1)" >> ./symfony-update.log
echo "$(date '+%Y-%m-%d %H:%M:%S') $(whoami) - current git tag:    $(git describe --tags --exact-match 2>&1)" >> ./symfony-update.log
echo "$(date '+%Y-%m-%d %H:%M:%S') $(whoami) - current git commit: $(head -n 1 ./.git/FETCH_HEAD | cut -c1-8)" >> ./symfony-update.log
```

After the server-update.sh completes:
```bash
echo "$(date '+%Y-%m-%d %H:%M:%S') $(whoami) - completed server-update!" >> ./symfony-update.log
echo "$(date '+%Y-%m-%d %H:%M:%S') $(whoami) - new git branch: $(git rev-parse --abbrev-ref HEAD 2>&1)" >> ./symfony-update.log
echo "$(date '+%Y-%m-%d %H:%M:%S') $(whoami) - new git tag:    $(git describe --tags --exact-match 2>&1)" >> ./symfony-update.log
echo "$(date '+%Y-%m-%d %H:%M:%S') $(whoami) - new git commit: $(head -n 1 ./.git/FETCH_HEAD | cut -c1-8)" >> ./symfony-update.log
echo "" >> ./symfony-update.log
```


server-update.sh script should write its updates to `symfony-update.log` on the root dir


### Mailer Configuration
Uses SwiftMailer (which is now deprecated in Symfony).

An .env variable called `MAILER_FROM` is required to be defined with a valid email address.

### Auth (`sshd`) Configurations
#### Verify `sshd` log verbosity
Open the config file at `/etc/ssh/sshd_config`:

```
...

LogLevel VERBOSE

...
```

That is the default for Ubuntu 22.04, but verify that is the case.

#### Configure `rsyslog` to log `sshd` to a special location
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