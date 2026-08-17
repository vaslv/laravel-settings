# Laravel Settings

Reusable Laravel package for storing typed settings in the database with caching and a clean API.

## Requirements

- PHP 8.2+
- Laravel 10-13

Laravel 13 requires PHP 8.3+ because the underlying `illuminate/*` 13.x components require it.

## Installation

```bash
composer require vaslv/laravel-settings
```

Run migrations:

```bash
php artisan migrate
```

The package migrations are loaded automatically, so this is all you need. Publishing
is optional:

```bash
php artisan vendor:publish --tag=settings-config
php artisan vendor:publish --tag=settings-migrations
```

If you publish the migrations, the bundled ones stop loading and your copies become
the only source. Publish only when you intend to edit them; otherwise both copies
would try to create the same table and `migrate` would fail.

## Configuration

`config/settings.php`

```php
return [
    'table' => 'settings',
    'encryption' => [
        'enabled' => false,
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'key' => 'settings',
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
Settings::set('site.legal_text', '# Legal', 'markdown');
Settings::set('legal_text', '# Legal'); // group = null
```

### Helper

```php
setting('site.legal_text');
setting('site.enabled', false);
setting('site.legal_text', '# Legal', 'markdown');
setting()->groups();
```

## Supported Types

- string
- boolean
- integer
- float
- html
- json
- markdown

Types are stored explicitly in the DB and cast on read.

`html` and `markdown` are raw string passthrough. They do not escape, sanitize, or
render anything; the type is a label telling your application how to treat the value.
Escaping stored HTML remains the caller's job at render time.

An unrecognised type is not an error. The value is returned as the stored string, so a
typo in a type name degrades quietly rather than throwing.

## Cache

The package caches all settings under one key and clears it automatically on `set`, and
on any `Setting` model save or delete.

Writes that bypass Eloquent events do not clear it. Query-builder `update`/`delete`,
`upsert`, raw SQL, and direct database edits leave the snapshot stale for up to the TTL.
Call `Settings::clearCache()` after those, or set `cache.enabled` to `false`.

The cache key is a single flat string. In a multi-tenant application that swaps database
connections while sharing one cache store, give each tenant its own `cache.key`,
otherwise one tenant's snapshot can be served to another.

## Encryption

Enable encryption to store raw values in encrypted form in the database. Values are decrypted on read.

```php
return [
    'encryption' => [
        'enabled' => true,
    ],
];
```

Each row records whether its own value is encrypted, in the `encrypted` column. Reads
follow that marker, never the current config flag, so the setting is safe to turn on
or off at any point:

- Turning it **on** leaves existing plaintext rows readable. They stay plaintext until
  the next write, which stores them encrypted.
- Turning it **off** leaves existing encrypted rows readable. They stay encrypted until
  the next write, which stores them as plaintext.
- Plaintext and encrypted rows coexist in the same table without special handling.

Empty strings and nulls are never encrypted, since ciphertext of an empty value carries
no secret and only wastes storage.

Encryption uses the application key. Rotating `APP_KEY` without re-encrypting will make
reads of encrypted rows throw, which is deliberate: failing loudly beats handing the
application a decrypted-looking value it cannot trust.

## Code Style

Code is formatted to comply with Laravel Pint.

## Compatibility

The package is tested against these combinations:

- Laravel 10 on PHP 8.2
- Laravel 11 on PHP 8.2
- Laravel 12 on PHP 8.2
- Laravel 13 on PHP 8.3
