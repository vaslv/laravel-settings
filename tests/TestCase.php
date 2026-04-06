<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Vaslv\LaravelSettings\SettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        $this->configureEnvironment($app);
    }

    protected function getPackageProviders($app): array
    {
        return [SettingsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('settings');

        $migration = require __DIR__.'/../database/migrations/2026_02_15_000000_create_settings_table.php';
        $migration->up();
    }

    private function configureEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('settings.cache.enabled', true);
        $app['config']->set('settings.cache.key', 'laravel-settings-test-cache');
        $app['config']->set('settings.encryption.enabled', false);
        $app['config']->set('settings.table', 'settings');
    }
}
