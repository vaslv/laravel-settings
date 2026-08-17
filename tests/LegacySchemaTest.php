<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vaslv\LaravelSettings\Facades\Settings;
use Vaslv\LaravelSettings\Models\Setting;
use Vaslv\LaravelSettings\SettingsManager;

/**
 * Upgrading the package without running the new migration must keep working. These
 * cover the table as it looked before the `encrypted` column existed.
 */
final class LegacySchemaTest extends TestCase
{
    public function test_encrypted_rows_still_round_trip_through_the_global_flag(): void
    {
        $this->legacyTable();
        DB::table('settings')->insert([
            'key' => 'sec.token',
            'group' => 'sec',
            'type' => 'string',
            'value' => Crypt::encrypt('top-secret', false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['config']->set('settings.encryption.enabled', true);
        $this->manager()->clearCache();

        $this->assertSame('top-secret', Settings::get('sec.token'));
        $this->assertSame('top-secret', Setting::query()->where('key', 'sec.token')->firstOrFail()->getValue());
    }

    public function test_migrating_afterwards_hands_over_to_the_per_row_marker(): void
    {
        $this->legacyTable();
        $this->assertFalse($this->manager()->tracksEncryptionPerRow());

        Schema::table('settings', function (Blueprint $table): void {
            $table->boolean('encrypted')->default(false);
        });
        $this->manager()->clearCache();

        // The probe is memoised per request, so clearCache() has to drop it or a
        // migration running in the same process would stay invisible.
        $this->assertTrue($this->manager()->tracksEncryptionPerRow());

        Settings::set('sec.token', 'after', 'string');
        $this->assertTrue(Setting::query()->where('key', 'sec.token')->firstOrFail()->encrypted === false);
        $this->assertSame('after', Settings::get('sec.token'));
    }

    public function test_reads_and_writes_work_without_the_marker_column(): void
    {
        $this->legacyTable();
        DB::table('settings')->insert([
            'key' => 'site.title',
            'group' => 'site',
            'type' => 'string',
            'value' => 'hello',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse($this->manager()->tracksEncryptionPerRow());
        $this->assertSame('hello', Settings::get('site.title'));

        Settings::set('site.title', 'changed');
        Settings::set('site.retries', 3);

        $this->assertSame('changed', Settings::get('site.title'));
        $this->assertSame(3, Settings::get('site.retries'));
        $this->assertFalse(Schema::hasColumn('settings', 'encrypted'));
    }

    public function test_writes_are_encrypted_when_the_global_flag_is_on(): void
    {
        $this->legacyTable();
        $this->app['config']->set('settings.encryption.enabled', true);
        $this->manager()->clearCache();

        Settings::set('sec.token', 'top-secret', 'string');

        $raw = DB::table('settings')->where('key', 'sec.token')->value('value');
        $this->assertNotSame('top-secret', $raw);
        $this->assertSame('top-secret', Settings::get('sec.token'));
    }

    private function legacyTable(): void
    {
        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('key')->unique();
            $table->string('group')->nullable()->index();
            $table->string('type');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->manager()->clearCache();
    }

    private function manager(): SettingsManager
    {
        return $this->app->make(SettingsManager::class);
    }
}
