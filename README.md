# Laravel Settings

Reusable Laravel package for storing typed settings in the database with caching and a clean API.

## Requirements

- PHP 8.2+
- Laravel 12-13

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

## Upgrading to 1.2

Run `php artisan migrate`. It adds an `encrypted` column that records, per row,
whether that value is encrypted, which is what makes the encryption setting safe to
toggle. If encryption is currently enabled, the migration marks existing rows as
encrypted so they keep reading correctly.

Forgetting the migration is not fatal. Without the column the package falls back to
the pre-1.2 behaviour, where encryption is decided globally by the config flag.

Three things change for new writes only. Values already in the database read exactly as
before.

- `boolean` writes now use the same rules as reads, so `"false"`, `"off"` and `"no"`
  store as false instead of true.
- `null` writes store a SQL `NULL` and read back as null instead of `''`, `false` or `0`.
- Writing a type that has no registered cast throws instead of storing the value as a
  string.

`cache.key` became a prefix rather than the whole key, so the first read after
upgrading repopulates the cache. If you evict the key by hand, use
`Settings::cacheKey()`.

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

Writing an unregistered type throws `InvalidArgumentException`, so a typo fails where
you made it. Reading stays permissive: a row whose type is not registered comes back
as the stored string, which keeps existing data readable.

`null` round-trips for every type. Writing null stores a SQL `NULL` and reading it
returns null rather than `''`, `false`, or `0`. The one exception is `json`, where null
is a value in its own right and is stored as the string `null`.

`boolean` reads and writes agree on what counts as true: `true`, `1`, `"1"`, `"true"`,
`"on"`, `"yes"`. Everything else is false, including the string `"false"`. Register a
custom cast if you need different rules.

`float` keeps full precision. Values are stored in the shortest form that casts back to
the identical float, so nothing is truncated to 14 significant digits. `NAN` and `INF`
are rejected, since neither has a string form that survives the round trip.

### Custom types

Bind your own `SettingCaster` with an extra map. Entries override the built-ins on
collision, and anything you do not list stays available.

```php
$this->app->singleton(SettingCaster::class, fn ($app) => new SettingCaster(
    $app,
    ['duration' => DurationCast::class],
));
```

A cast implements `get(mixed $value): mixed` and `set(mixed $value): ?string`.

## Cache

The package caches all settings under one key and clears it automatically on `set`, and
on any `Setting` model save or delete.

Eviction is deferred to the outermost commit when the write happens inside a
transaction, so a concurrent reader cannot cache state that is not committed yet. A
rolled back write leaves the cache untouched.

Writes that bypass Eloquent events do not clear it. Query-builder `update`/`delete`,
`upsert`, raw SQL, and direct database edits leave the snapshot stale for up to the TTL.
Call `Settings::clearCache()` after those, or set `cache.enabled` to `false`.

`cache.key` is a prefix, not the whole key. The connection name, database name and
table are appended, so a multi-tenant application that swaps connections, or swaps the
database behind one connection, gets a separate entry per tenant instead of serving one
tenant's settings to another. `Settings::cacheKey()` returns the key actually in use.

## Encryption

Enable encryption to store raw values in encrypted form in the database. Values are decrypted on read.

```php
return [
    'encryption' => [
        'enabled' => true,
    ],
];
```

Each row records whether its own value is encrypted, in the `encrypted` column added in
1.2. Reads follow that marker, never the current config flag, so the setting is safe to
turn on or off at any point:

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

On a table without the marker column, the package falls back to deciding encryption
globally from the config flag. `Settings::tracksEncryptionPerRow()` reports which mode
is active.

## Code Style

Code is formatted to comply with Laravel Pint.

## Compatibility

The package is tested against these combinations:

- Laravel 12 on PHP 8.2
- Laravel 13 on PHP 8.3
