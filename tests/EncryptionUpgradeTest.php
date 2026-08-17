<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vaslv\LaravelSettings\Facades\Settings;

final class EncryptionUpgradeTest extends TestCase
{
    public function test_the_backfill_claims_pre_existing_ciphertext_rows(): void
    {
        // Reproduce the pre-upgrade table: no marker column, and rows written while the
        // old global encryption switch was on.
        $this->recreateLegacyTable();
        $this->assertFalse(Schema::hasColumn('settings', 'encrypted'));

        DB::table('settings')->insert([
            'key' => 'sec.token',
            'group' => 'sec',
            'type' => 'string',
            'value' => Crypt::encrypt('top-secret', false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['config']->set('settings.encryption.enabled', true);
        $this->runAddEncryptedMigration();

        $this->assertTrue(Schema::hasColumn('settings', 'encrypted'));
        $this->assertSame(1, (int) DB::table('settings')->where('encrypted', true)->count());
        $this->assertSame('top-secret', Settings::get('sec.token'));
    }

    public function test_the_backfill_leaves_plaintext_rows_alone_when_encryption_is_off(): void
    {
        $this->recreateLegacyTable();

        DB::table('settings')->insert([
            'key' => 'site.title',
            'group' => 'site',
            'type' => 'string',
            'value' => 'plain-title',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['config']->set('settings.encryption.enabled', false);
        $this->runAddEncryptedMigration();

        $this->assertSame(0, (int) DB::table('settings')->where('encrypted', true)->count());
        $this->assertSame('plain-title', Settings::get('site.title'));
    }

    /**
     * Rebuild the table as it looked before the marker column existed. Dropping the
     * column instead would need doctrine/dbal on the Laravel 10 leg of the CI matrix.
     */
    private function recreateLegacyTable(): void
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
    }

    private function runAddEncryptedMigration(): void
    {
        $migration = require __DIR__.'/../database/migrations/2026_08_17_000000_add_encrypted_to_settings_table.php';
        $migration->up();
    }
}
