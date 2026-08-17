<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Vaslv\LaravelSettings\Models\Setting;

final class SettingsManager
{
    private CacheRepository $cache;

    private SettingCaster $caster;

    private ConfigRepository $config;

    private Encrypter $encrypter;

    public function __construct(
        CacheManager $cache,
        ConfigRepository $config,
        SettingCaster $caster,
        Encrypter $encrypter
    ) {
        $this->cache = $cache->store();
        $this->config = $config;
        $this->caster = $caster;
        $this->encrypter = $encrypter;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->castMany($this->allRaw());
    }

    /**
     * The key is scoped by connection, database and table. A flat key is shared by
     * every tenant in an application that swaps connections, or swaps the database
     * behind one connection, while pointing at a single cache store: whoever primes
     * it first serves their settings to everyone else.
     */
    public function cacheKey(): string
    {
        $base = (string) $this->config->get('settings.cache.key', 'settings');
        $connection = (string) $this->config->get('database.default', 'default');
        $table = (string) $this->config->get('settings.table', 'settings');

        return implode(':', [$base, $connection, $this->databaseName($connection), $table]);
    }

    public function castValue(string $type, ?string $value, bool $encrypted = false): mixed
    {
        return $this->getCastValue($type, $value, $encrypted);
    }

    public function clearCache(): void
    {
        // Deliberately not guarded by settings.cache.enabled: a write that happens
        // while caching is off must still evict whatever an earlier enabled run
        // left behind, otherwise re-enabling the cache resurrects a stale snapshot.
        $this->cache->forget($this->cacheKey());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->allRaw();

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        return $this->getCastValue(
            $settings[$key]['type'],
            $settings[$key]['value'],
            $settings[$key]['encrypted']
        );
    }

    /** @return array<string, mixed> */
    public function group(string $group): array
    {
        $settings = $this->allRaw();
        $filtered = array_filter($settings, fn (array $item): bool => $item['group'] === $group);

        return $this->castMany($filtered);
    }

    /** @return array<int, string> */
    public function groups(): array
    {
        $settings = $this->allRaw();
        $groups = array_map(fn (array $item): ?string => $item['group'], $settings);
        $named = array_filter($groups, fn (?string $group): bool => $group !== null && $group !== '');

        return array_values(array_unique($named));
    }

    public function has(string $key): bool
    {
        $settings = $this->allRaw();

        return array_key_exists($key, $settings);
    }

    public function set(string $key, mixed $value, ?string $type = null): void
    {
        $setting = Setting::query()->where('key', $key)->first();

        $type = $type ?? $setting?->type ?? $this->inferType($value);
        $group = $setting?->group ?? $this->inferGroup($key);

        $plainValue = $this->toRawValue($type, $value);
        $encrypted = $this->shouldEncrypt($plainValue);

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'type' => $type,
                'encrypted' => $encrypted,
                'value' => $encrypted ? $this->encrypter->encrypt($plainValue, false) : $plainValue,
            ]
        );
    }

    /** @return array<string, array{group: string|null, type: string, encrypted: bool, value: string|null}> */
    private function allRaw(): array
    {
        if (! $this->isCacheEnabled()) {
            return $this->loadAllRaw();
        }

        $ttl = (int) $this->config->get('settings.cache.ttl', 3600);

        return $this->cache->remember($this->cacheKey(), $ttl, function (): array {
            return $this->loadAllRaw();
        });
    }

    /** @param array<string, array{group: string|null, type: string, encrypted: bool, value: string|null}> $settings */
    private function castMany(array $settings): array
    {
        return array_map(function (array $item): mixed {
            return $this->getCastValue($item['type'], $item['value'], $item['encrypted']);
        }, $settings);
    }

    private function databaseName(string $connection): string
    {
        $database = (string) $this->config->get("database.connections.{$connection}.database", '');

        return (string) preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename($database));
    }

    /**
     * Decryption is driven by the per-row marker, never by the current config flag.
     * Toggling settings.encryption.enabled therefore only changes how NEW writes are
     * stored; rows already on disk keep being read the way they were written.
     */
    private function decryptIfNeeded(?string $value, bool $encrypted): ?string
    {
        if (! $encrypted || $value === null || $value === '') {
            return $value;
        }

        return (string) $this->encrypter->decrypt($value, false);
    }

    private function getCastValue(string $type, ?string $value, bool $encrypted): mixed
    {
        $rawValue = $this->decryptIfNeeded($value, $encrypted);

        // A NULL column means "no value", whatever the declared type says. Handing it
        // to a cast would turn it into '' for strings, false for booleans and 0 for
        // numbers, so null could never survive a round trip.
        if ($rawValue === null) {
            return null;
        }

        if (! $this->caster->has($type)) {
            return $rawValue;
        }

        return $this->caster->resolve($type)->get($rawValue);
    }

    private function inferGroup(string $key): ?string
    {
        if (! str_contains($key, '.')) {
            return null;
        }

        $segments = explode('.', $key);

        return Arr::first($segments);
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => SettingType::BOOLEAN->value,
            is_int($value) => SettingType::INTEGER->value,
            is_float($value) => SettingType::FLOAT->value,
            is_array($value), is_object($value) => SettingType::JSON->value,
            default => SettingType::STRING->value,
        };
    }

    private function isCacheEnabled(): bool
    {
        return (bool) $this->config->get('settings.cache.enabled', true);
    }

    private function isEncryptionEnabled(): bool
    {
        return (bool) $this->config->get('settings.encryption.enabled', false);
    }

    /** @return array<string, array{group: string|null, type: string, encrypted: bool, value: string|null}> */
    private function loadAllRaw(): array
    {
        return Setting::query()
            ->get(['key', 'group', 'type', 'encrypted', 'value'])
            ->keyBy('key')
            ->map(fn (Setting $setting): array => [
                'group' => $setting->group,
                'type' => $setting->type,
                'encrypted' => (bool) $setting->encrypted,
                'value' => $setting->value,
            ])
            ->all();
    }

    private function shouldEncrypt(?string $plainValue): bool
    {
        return $this->isEncryptionEnabled() && $plainValue !== null && $plainValue !== '';
    }

    /**
     * Unknown types are rejected here rather than quietly stringified. A typo in a
     * type name used to turn a bool or an array into a lossy string with no signal.
     * Reads stay permissive so rows already carrying an unknown type remain readable.
     */
    private function toRawValue(string $type, mixed $value): ?string
    {
        if (! $this->caster->has($type)) {
            throw new InvalidArgumentException("Unknown setting type: {$type}");
        }

        return $this->caster->resolve($type)->set($value);
    }
}
