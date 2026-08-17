<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use Vaslv\LaravelSettings\Facades\Settings;
use Vaslv\LaravelSettings\Models\Setting;
use Vaslv\LaravelSettings\SettingsManager;

final class EncryptionTest extends TestCase
{
    public function test_disabling_encryption_still_reads_previously_encrypted_rows(): void
    {
        $this->enableEncryption();
        Settings::set('sec.require_2fa', true, 'boolean');
        Settings::set('sec.max_attempts', 5, 'integer');
        Settings::set('sec.payload', ['scope' => 'admin'], 'json');
        Settings::set('sec.token', 'top-secret', 'string');

        $this->disableEncryption();

        // Before the per-row marker, reads went through the global config flag. Turning
        // it off handed the raw ciphertext to the casts, so require_2fa read back as
        // false, max_attempts as 0, and json threw. Security controls failed open.
        $this->assertTrue(Settings::get('sec.require_2fa'));
        $this->assertSame(5, Settings::get('sec.max_attempts'));
        $this->assertSame(['scope' => 'admin'], Settings::get('sec.payload'));
        $this->assertSame('top-secret', Settings::get('sec.token'));
    }

    public function test_empty_and_null_values_are_not_encrypted(): void
    {
        $this->enableEncryption();

        Settings::set('sec.blank', '', 'string');

        $row = Setting::query()->where('key', 'sec.blank')->firstOrFail();
        $this->assertFalse($row->encrypted);
        $this->assertSame('', Settings::get('sec.blank'));
    }

    public function test_enabling_encryption_still_reads_existing_plaintext_rows(): void
    {
        Settings::set('legacy.token', 'plaintext-value', 'string');
        Settings::set('legacy.count', 11, 'integer');

        $this->enableEncryption();

        // Before the per-row marker this threw DecryptException on every read, taking
        // down every request that touched settings.
        $this->assertSame('plaintext-value', Settings::get('legacy.token'));
        $this->assertSame(11, Settings::get('legacy.count'));
    }

    public function test_mixed_plaintext_and_ciphertext_rows_coexist(): void
    {
        Settings::set('mixed.plain', 'before', 'string');

        $this->enableEncryption();
        Settings::set('mixed.secret', 'after', 'string');

        $plain = Setting::query()->where('key', 'mixed.plain')->firstOrFail();
        $secret = Setting::query()->where('key', 'mixed.secret')->firstOrFail();

        $this->assertFalse($plain->encrypted);
        $this->assertTrue($secret->encrypted);
        $this->assertSame('before', Settings::get('mixed.plain'));
        $this->assertSame('after', Settings::get('mixed.secret'));
    }

    public function test_rewriting_a_row_adopts_the_current_encryption_mode(): void
    {
        Settings::set('sec.rotating', 'v1', 'string');
        $this->assertFalse(Setting::query()->where('key', 'sec.rotating')->firstOrFail()->encrypted);

        $this->enableEncryption();
        Settings::set('sec.rotating', 'v2', 'string');

        $row = Setting::query()->where('key', 'sec.rotating')->firstOrFail();
        $this->assertTrue($row->encrypted);
        $this->assertSame('v2', Settings::get('sec.rotating'));

        $this->disableEncryption();
        Settings::set('sec.rotating', 'v3', 'string');

        $row = Setting::query()->where('key', 'sec.rotating')->firstOrFail();
        $this->assertFalse($row->encrypted);
        $this->assertSame('v3', $row->getRawOriginal('value'));
        $this->assertSame('v3', Settings::get('sec.rotating'));
    }

    public function test_values_are_written_as_ciphertext_when_encryption_is_on(): void
    {
        $this->enableEncryption();

        Settings::set('sec.token', 'top-secret', 'string');

        $row = Setting::query()->where('key', 'sec.token')->firstOrFail();
        $this->assertTrue($row->encrypted);
        $this->assertNotSame('top-secret', $row->getRawOriginal('value'));
        $this->assertSame('top-secret', Settings::get('sec.token'));
        $this->assertSame('top-secret', $row->getValue());
    }

    private function disableEncryption(): void
    {
        $this->app['config']->set('settings.encryption.enabled', false);
        $this->app->make(SettingsManager::class)->clearCache();
    }

    private function enableEncryption(): void
    {
        $this->app['config']->set('settings.encryption.enabled', true);
        $this->app->make(SettingsManager::class)->clearCache();
    }
}
