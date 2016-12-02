# MYSQL-SYNCER (Laravel 5 Package)

[![Build Status](https://travis-ci.org/Jezzis/mysql-syncer.svg?branch=master)](https://travis-ci.org/Jezzis/mysql-syncer)
[![Coverage Status](https://coveralls.io/repos/github/Jezzis/mysql-syncer/badge.svg?branch=master)](https://coveralls.io/github/Jezzis/mysql-syncer?branch=master)
[![Latest Stable Version](https://poser.pugx.org/jezzis/mysqlsyncer/v/stable)](https://packagist.org/packages/jezzis/mysqlsyncer)
[![License](https://poser.pugx.org/jezzis/mysqlsyncer/license)](https://packagist.org/packages/jezzis/mysqlsyncer)
[![Total Downloads](https://poser.pugx.org/jezzis/mysqlsyncer/downloads)](https://packagist.org/packages/jezzis/mysqlsyncer)


This tool provide an easy way to synchronize database structure including tables, views, functions, procedures for **Laravel 5**.

## Installation

 1) In order to install mysql-syncer, just add
```json
  "jezzis/mysqlsyncer":"dev-master"
```
to your composer.json. Then run `composer install` or `composer update`.

 2) In your `config/app.php` add
```php
    Jezzis\MysqlSyncer\MysqlSyncerServiceProvider::class
```

## Configuration

If you want to customize the base path of the sql file, please copy src/config.php to laravel project config directory, rename it to msyncer.php
```php
return [

    'sql_path' => './', // sql file base path where MySQL-Syncer is looking for.

    'driver' => 'mysql', // connection driver, currently only supports MySQL.
];
```

## Usage

  run the command in console:
```bash
    #php artisan db:sync [options] [--] <file>
```

### Params

- file: The file path of the sql file, without .sql extension

### Options

- --drop: allow drop tables, columns, keys, views, functions, procedure

## Troubleshooting
  Grant select privilege on mysql.func and mysql.proc to make sure the tools can fetch the definition of functions & procedure.

## License

mysql-syncer is free software distributed under the terms of the MIT license.

## Contribution guidelines

Support follows PSR-1 and PSR-4 PHP coding standards, and semantic versioning.

Please report any issue you find in the issues page.
Pull requests are welcome.