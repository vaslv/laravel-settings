<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use InvalidArgumentException;
use Vaslv\LaravelSettings\Facades\Settings;
use Vaslv\LaravelSettings\Models\Setting;
use Vaslv\LaravelSettings\SettingsManager;

final class SettingsManagerTest extends TestCase
{
    public function test_all_returns_every_key_cast_to_its_type(): void
    {
        Settings::set('site.enabled', true);
        Settings::set('site.retries', 3);
        Settings::set('mail.from', 'hi@example.com');

        // Insertion order, not alphabetical: the snapshot is keyed straight off the
        // default query order, which is the primary key.
        $this->assertSame([
            'site.enabled' => true,
            'site.retries' => 3,
            'mail.from' => 'hi@example.com',
        ], Settings::all());
    }

    public function test_get_returns_the_default_for_a_missing_key(): void
    {
        $this->assertNull(Settings::get('nope.missing'));
        $this->assertSame('fallback', Settings::get('nope.missing', 'fallback'));
        $this->assertFalse(Settings::has('nope.missing'));
    }

    public function test_groups_keeps_a_group_literally_named_zero(): void
    {
        Settings::set('0.alpha', 'a');
        Settings::set('site.beta', 'b');

        // A callback-free array_filter drops "0" along with the nulls, because the
        // string "0" is falsy in PHP. A key like 0.alpha lost its whole group.
        $this->assertSame(['0', 'site'], Settings::groups());
    }

    public function test_groups_skips_keys_without_a_group(): void
    {
        Settings::set('site.title', 'a');
        Settings::set('standalone', 'b');

        $this->assertSame([null], [Setting::query()->where('key', 'standalone')->value('group')]);
        $this->assertSame(['site'], Settings::groups());
    }

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

    public function test_setting_helper_rejects_a_null_key(): void
    {
        // setting(null) used to reach SettingsManager::get(string $key) and blow up
        // with a TypeError pointing at helpers.php rather than at the caller.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('setting() requires a key');

        setting(null);
    }

    public function test_setting_helper_writes_and_returns_the_value(): void
    {
        $this->assertSame('written', setting('site.title', 'written'));
        $this->assertSame('written', setting('site.title'));

        setting('site.body', '# Legal', 'markdown');
        $this->assertSame('markdown', Setting::query()->where('key', 'site.body')->value('type'));
    }

    public function test_setting_model_get_value_decrypts_when_encryption_is_enabled(): void
    {
        $this->app['config']->set('settings.encryption.enabled', true);

        Settings::set('secret.token', 'top-secret', 'string');

        $setting = Setting::query()->where('key', 'secret.token')->firstOrFail();

        $this->assertNotSame('top-secret', $setting->getRawOriginal('value'));
        $this->assertSame('top-secret', $setting->getValue());
    }

    public function test_the_group_is_the_first_dotted_segment_and_null_without_a_dot(): void
    {
        Settings::set('mail.smtp.host', 'localhost');
        Settings::set('standalone', 'x');

        $this->assertSame('mail', Setting::query()->where('key', 'mail.smtp.host')->value('group'));
        $this->assertNull(Setting::query()->where('key', 'standalone')->value('group'));
        $this->assertSame(['mail.smtp.host' => 'localhost'], Settings::group('mail'));
    }
}
