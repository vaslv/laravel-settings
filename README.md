# Laravel Settings

Reusable Laravel package for storing typed settings in the database with caching and a clean API.

## Requirements

- PHP 8.2+
- Laravel 10+

## Installation

```bash
composer require vaslv/laravel-settings
```

Publish the config and migration:

```bash
php artisan vendor:publish --tag=settings-config
php artisan vendor:publish --tag=settings-migrations
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

`config/settings.php`

```php
return [
    'table' => 'settings',
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'key' => 'app_settings',
    ],
];
```

If you change `table`, the published migration will use the configured name.

## Usage

### Facade

```php
use Settings;

Settings::get('site.legal_text');
Settings::set('site.enabled', true);
Settings::setWithType('site.legal_text', '# Legal', 'markdown');
```

### Helper

```php
setting('site.legal_text');
setting('site.enabled', false);
```

## Supported Types

- string
- boolean
- integer
- float
- json
- markdown

Types are stored explicitly in the DB and cast on read.

## Cache

The package caches all settings under one key and clears it automatically on `set` or `setWithType`.

## Code Style

Code is formatted to comply with Laravel Pint.
