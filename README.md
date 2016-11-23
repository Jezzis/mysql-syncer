# MYSQL-SYNCER (Laravel 5 Package)

this tool provide an easy way to synchronize database structure including tables, views, functions, procedures for **Laravel 5**.

## Installation

 1) In order to install mysql-syncer, just add
```json
  "jezzis/mysqlsyncer": "dev-master"
```
to your composer.json. Then run `composer install` or `composer update`.

 2) In your `config/app.php` add
```php
    Jezzis\Mysqlsyncer\MysqlsyncerServiceProvider::class
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

## License

mysql-syncer is free software distributed under the terms of the MIT license.

## Contribution guidelines

Support follows PSR-1 and PSR-4 PHP coding standards, and semantic versioning.

Please report any issue you find in the issues page.
Pull requests are welcome.