<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use ReflectionMethod;
use Vaslv\LaravelSettings\SettingsServiceProvider;

final class SettingsServiceProviderTest extends TestCase
{
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
}
