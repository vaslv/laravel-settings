<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Vaslv\LaravelSettings\Facades\Settings;
use Vaslv\LaravelSettings\Models\Setting;
use Vaslv\LaravelSettings\SettingsManager;

final class CacheTest extends TestCase
{
    public function test_a_rolled_back_write_leaves_the_cache_untouched(): void
    {
        Settings::set('site.title', 'committed');
        Settings::get('site.title');

        DB::beginTransaction();
        Settings::set('site.title', 'rolled-back');
        DB::rollBack();

        // The eviction was queued on commit, which never came, so the snapshot that
        // was correct all along is still there and still correct.
        $this->assertSame('committed', Settings::get('site.title'));
    }

    public function test_cache_eviction_waits_for_the_outermost_commit(): void
    {
        Settings::set('site.title', 'before');
        Settings::get('site.title');
        $this->assertTrue(Cache::has(Settings::cacheKey()));

        DB::beginTransaction();
        Settings::set('site.title', 'after');

        // Evicting mid-transaction would let a concurrent reader repopulate the key
        // with state no other connection can see yet, and that snapshot would then
        // outlive the commit for the whole TTL.
        $this->assertTrue(Cache::has(Settings::cacheKey()));

        DB::commit();

        $this->assertFalse(Cache::has(Settings::cacheKey()));
        $this->assertSame('after', Settings::get('site.title'));
    }

    public function test_clear_cache_evicts_even_while_caching_is_disabled(): void
    {
        Settings::set('site.title', 'cached');
        Settings::get('site.title');
        $this->assertTrue(Cache::has(Settings::cacheKey()));

        $this->app['config']->set('settings.cache.enabled', false);
        $this->app->make(SettingsManager::class)->clearCache();

        // The guard used to return early here, so the stale snapshot survived and came
        // back the moment caching was switched on again.
        $this->assertFalse(Cache::has(Settings::cacheKey()));
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
        $this->assertFalse(Cache::has(Settings::cacheKey()));
    }

    public function test_the_configured_cache_key_is_the_one_used(): void
    {
        $this->app['config']->set('settings.cache.key', 'custom-settings-key');

        Settings::set('site.title', 'value');
        Settings::get('site.title');

        // The configured value is the prefix, not the whole key: connection, database
        // and table are appended so two tenants sharing a cache store cannot collide.
        $this->assertStringStartsWith('custom-settings-key:', Settings::cacheKey());
        $this->assertTrue(Cache::has(Settings::cacheKey()));
    }

    public function test_the_key_is_scoped_by_connection_database_and_table(): void
    {
        $base = Settings::cacheKey();

        $this->app['config']->set('settings.table', 'other_settings');
        $this->assertNotSame($base, Settings::cacheKey());

        $this->app['config']->set('settings.table', 'settings');
        $this->app['config']->set('database.connections.testing.database', '/var/data/tenant_42.sqlite');

        // Swapping the database behind one connection is how most tenancy packages
        // work, so the database name has to be part of the key too.
        $scoped = Settings::cacheKey();
        $this->assertNotSame($base, $scoped);
        $this->assertStringContainsString('tenant_42.sqlite', $scoped);
    }

    public function test_writes_through_the_manager_invalidate_the_cached_snapshot(): void
    {
        Settings::set('site.theme', 'light');
        $this->assertSame('light', Settings::all()['site.theme']);

        Settings::set('site.theme', 'dark');

        $this->assertSame('dark', Settings::all()['site.theme']);
    }
}
