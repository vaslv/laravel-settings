<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings;

use Illuminate\Support\ServiceProvider;

final class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/settings.php' => $this->app->configPath('settings.php'),
        ], 'settings-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/settings.php', 'settings');

        $this->app->singleton(SettingCaster::class);
        $this->app->singleton(SettingsManager::class);

        $this->app->alias(SettingsManager::class, 'settings.manager');
    }
}
