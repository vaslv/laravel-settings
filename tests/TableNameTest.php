<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use Illuminate\Support\Facades\Schema;
use Vaslv\LaravelSettings\Facades\Settings;
use Vaslv\LaravelSettings\Models\Setting;

final class TableNameTest extends TestCase
{
    public function test_the_configured_table_name_is_used_end_to_end(): void
    {
        $this->assertTrue(Schema::hasTable('app_settings'));
        $this->assertFalse(Schema::hasTable('settings'));
        $this->assertSame('app_settings', (new Setting)->getTable());

        Settings::set('site.title', 'renamed');

        $this->assertSame('renamed', Settings::get('site.title'));
    }

    public function test_the_encrypted_column_exists_on_the_renamed_table(): void
    {
        // The second migration resolves its target from the same config key, so a
        // renamed table must still pick up the encryption marker column.
        $this->assertTrue(Schema::hasColumn('app_settings', 'encrypted'));
    }

    protected function configureEnvironment($app): void
    {
        parent::configureEnvironment($app);

        $app['config']->set('settings.table', 'app_settings');
    }
}
