# MYSQL-SYNCER (Laravel 5 Package)

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

    /*
    |--------------------------------------------------------------------------
    | Mysql-Syncer source sql file base path
    |--------------------------------------------------------------------------
    |
    | This is the sql file base path where Mysql-Syncer is looking for.
    |
    |
    */
    'sql_path' => './',
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