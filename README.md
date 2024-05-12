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



## TO-DO
- Ubuntu Utility
  - Check OS version compatibility
  - Pull SSH Authorized Keys file
  - Pull Auth.log file