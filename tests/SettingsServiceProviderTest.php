<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Vaslv\LaravelSettings\SettingsManager;
use Vaslv\LaravelSettings\SettingsServiceProvider;

final class SettingsServiceProviderTest extends TestCase
{
    public function test_bundled_migrations_are_loaded_when_nothing_is_published(): void
    {
        // TestCase::setUp() runs `migrate` with no published copies present, so the
        // table can only exist if the provider registered its own migration path.
        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertTrue(Schema::hasColumn('settings', 'encrypted'));
        $this->assertContains(
            realpath(__DIR__.'/../database/migrations'),
            array_map(fn (string $path): string => (string) realpath($path), $this->app['migrator']->paths())
        );
    }

    public function test_migration_publish_paths_are_sorted_and_get_unique_timestamps(): void
    {
        $provider = new SettingsServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'migrationPublishes');
        $method->setAccessible(true);

        /** @var array<string, string> $publishes */
        $publishes = $method->invoke($provider, [
            '/tmp/2026_02_15_000000_create_z_table.php',
            '/tmp/2024_01_01_000000_create_a_table.php',
        ]);

        $targets = array_values($publishes);
        $firstFileName = basename($targets[0]);
        $secondFileName = basename($targets[1]);
        $firstTimestamp = substr($firstFileName, 0, 19);
        $secondTimestamp = substr($secondFileName, 0, 19);

        $this->assertSame([
            '/tmp/2024_01_01_000000_create_a_table.php',
            '/tmp/2026_02_15_000000_create_z_table.php',
        ], array_keys($publishes));
        $this->assertStringEndsWith('_create_a_table.php', $firstFileName);
        $this->assertStringEndsWith('_create_z_table.php', $secondFileName);
        $this->assertNotSame($firstTimestamp, $secondTimestamp);
        $this->assertTrue($firstTimestamp < $secondTimestamp);
    }

    public function test_publishing_the_migrations_stops_the_bundled_ones_from_loading(): void
    {
        $appMigrations = $this->app->databasePath('migrations');
        @mkdir($appMigrations, 0777, true);
        $published = $appMigrations.'/2030_01_01_000000_create_settings_table.php';
        file_put_contents($published, "<?php\n");

        try {
            $provider = new SettingsServiceProvider($this->app);
            $method = new ReflectionMethod($provider, 'migrationsArePublished');
            $method->setAccessible(true);

            // Both copies loading is what produced "table already exists" for anyone who
            // followed the README and ran vendor:publish before migrate.
            $this->assertTrue($method->invoke($provider));
        } finally {
            @unlink($published);
        }
    }

    public function test_the_bundled_migrations_are_not_treated_as_published_by_default(): void
    {
        $provider = new SettingsServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'migrationsArePublished');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($provider));
    }

    public function test_the_manager_is_a_singleton_reachable_through_every_entry_point(): void
    {
        $viaClass = $this->app->make(SettingsManager::class);

        $this->assertSame($viaClass, $this->app->make('settings.manager'));
        $this->assertSame($viaClass, setting());
    }
}
