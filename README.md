# Angle Audit Bundle
Audit Bundle for Symfony 5.4+ to produce automated reports for OS (Ubuntu) and DB (MySQL) privileged access

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


## Requirements
Standard Angle symfony application setup

MySQL Database
Ubuntu 18.04 - 22.04 Operating System
SwiftMailer

Application is installed in `/var/www/`


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



## TO-DO
- Ubuntu Utility
  - Check OS version compatibility
  - Pull SSH Authorized Keys file
  - Pull Auth.log file