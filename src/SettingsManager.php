<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Arr;
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

    public function clearCache(): void
    {
        if (! $this->isCacheEnabled()) {
            return;
        }

        $this->cache->forget($this->cacheKey());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->allRaw();

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        return $this->getCastValue($settings[$key]['type'], $settings[$key]['value']);
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

        return array_values(array_filter(array_unique($groups)));
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

        $rawValue = $this->setCastValue($type, $value);

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'type' => $type,
                'value' => $rawValue,
            ]
        );
    }

    /** @return array<string, array{group: string|null, type: string, value: string|null}> */
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

    private function cacheKey(): string
    {
        return (string) $this->config->get('settings.cache.key', 'app_settings');
    }

    /** @param array<string, array{group: string|null, type: string, value: string|null}> $settings */
    private function castMany(array $settings): array
    {
        return array_map(function ($item) {
            return $this->getCastValue($item['type'], $item['value']);
        }, $settings);
    }

    private function decryptIfNeeded(?string $value): ?string
    {
        if ($value === null || $value === '' || ! $this->isEncryptionEnabled()) {
            return $value;
        }

        return (string) $this->encrypter->decrypt($value, false);
    }

    private function encryptIfNeeded(?string $value): ?string
    {
        if ($value === null || $value === '' || ! $this->isEncryptionEnabled()) {
            return $value;
        }

        return $this->encrypter->encrypt($value, false);
    }

    private function getCastValue(string $type, ?string $value): mixed
    {
        $rawValue = $this->decryptIfNeeded($value);

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

    /** @return array<string, array{group: string|null, type: string, value: string|null}> */
    private function loadAllRaw(): array
    {
        return Setting::query()
            ->get(['key', 'group', 'type', 'value'])
            ->keyBy('key')
            ->map(fn (Setting $setting): array => [
                'group' => $setting->group,
                'type' => $setting->type,
                'value' => $setting->value,
            ])
            ->all();
    }

    private function setCastValue(string $type, mixed $value): ?string
    {
        if (! $this->caster->has($type)) {
            return $this->encryptIfNeeded($value === null ? null : (string) $value);
        }

        return $this->encryptIfNeeded($this->caster->resolve($type)->set($value));
    }
}
