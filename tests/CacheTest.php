<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use Illuminate\Support\Facades\Cache;
use Vaslv\LaravelSettings\Facades\Settings;
use Vaslv\LaravelSettings\Models\Setting;
use Vaslv\LaravelSettings\SettingsManager;

final class CacheTest extends TestCase
{
    public function test_clear_cache_evicts_even_while_caching_is_disabled(): void
    {
        Settings::set('site.title', 'cached');
        Settings::get('site.title');
        $this->assertTrue(Cache::has('laravel-settings-test-cache'));

        $this->app['config']->set('settings.cache.enabled', false);
        $this->app->make(SettingsManager::class)->clearCache();

        // The guard used to return early here, so the stale snapshot survived and came
        // back the moment caching was switched on again.
        $this->assertFalse(Cache::has('laravel-settings-test-cache'));
    }

    public function test_deleting_a_model_busts_the_cache(): void
    {
        Settings::set('site.title', 'present');
        $this->assertSame('present', Settings::get('site.title'));

        Setting::query()->where('key', 'site.title')->firstOrFail()->delete();

        $this->assertNull(Settings::get('site.title'));
        $this->assertFalse(Settings::has('site.title'));
    }

    public function test_reads_hit_the_database_every_time_when_caching_is_disabled(): void
    {
        $this->app['config']->set('settings.cache.enabled', false);

        Settings::set('site.title', 'first');
        $this->assertSame('first', Settings::get('site.title'));

        Setting::query()->where('key', 'site.title')->update(['value' => 'second']);

        // No cache means no staleness window, so even a query-builder write is visible.
        $this->assertSame('second', Settings::get('site.title'));
        $this->assertFalse(Cache::has('laravel-settings-test-cache'));
    }

    public function test_the_configured_cache_key_is_the_one_used(): void
    {
        $this->app['config']->set('settings.cache.key', 'custom-settings-key');

        Settings::set('site.title', 'value');
        Settings::get('site.title');

        $this->assertTrue(Cache::has('custom-settings-key'));
    }

    public function test_writes_through_the_manager_invalidate_the_cached_snapshot(): void
    {
        Settings::set('site.theme', 'light');
        $this->assertSame('light', Settings::all()['site.theme']);

        Settings::set('site.theme', 'dark');

        $this->assertSame('dark', Settings::all()['site.theme']);
    }
}
