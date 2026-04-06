<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use Vaslv\LaravelSettings\Facades\Settings;
use Vaslv\LaravelSettings\Models\Setting;
use Vaslv\LaravelSettings\SettingsManager;

final class SettingsManagerTest extends TestCase
{
    public function test_it_invalidates_cached_settings_after_update(): void
    {
        /** @var SettingsManager $manager */
        $manager = $this->app->make(SettingsManager::class);

        $manager->set('site.theme', 'light');
        $this->assertSame('light', $manager->get('site.theme'));

        $manager->all();
        $manager->set('site.theme', 'dark');

        $this->assertSame('dark', $manager->get('site.theme'));
    }

    public function test_it_reads_and_groups_typed_settings(): void
    {
        Settings::set('site.enabled', true);
        Settings::set('site.title', 'Laravel Settings');

        $this->assertTrue(Settings::get('site.enabled'));
        $this->assertSame('Laravel Settings', setting('site.title'));
        $this->assertSame(['site'], setting()->groups());
        $this->assertSame([
            'site.enabled' => true,
            'site.title' => 'Laravel Settings',
        ], Settings::group('site'));
    }

    public function test_setting_model_get_value_decrypts_when_encryption_is_enabled(): void
    {
        $this->app['config']->set('settings.encryption.enabled', true);

        Settings::set('secret.token', 'top-secret', 'string');

        $setting = Setting::query()->where('key', 'secret.token')->firstOrFail();

        $this->assertNotSame('top-secret', $setting->getRawOriginal('value'));
        $this->assertSame('top-secret', $setting->getValue());
    }
}
