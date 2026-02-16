<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings;

use Illuminate\Support\ServiceProvider;

final class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/settings.php', 'settings');

        $this->app->singleton(SettingCaster::class);
        $this->app->singleton(SettingsManager::class);

        $this->app->alias(SettingsManager::class, 'settings.manager');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/settings.php' => $this->app->configPath('settings.php'),
        ], 'settings-config');

        $this->publishes([
            __DIR__.'/../database/migrations/2026_02_15_000000_create_settings_table.php' => $this->app->databasePath('migrations/2026_02_15_000000_create_settings_table.php'),
        ], 'settings-migrations');
    }
}
