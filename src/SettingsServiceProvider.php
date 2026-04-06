<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings;

use Illuminate\Support\ServiceProvider;

final class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $migrationPaths = glob(__DIR__.'/../database/migrations/*.php') ?: [];

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/settings.php' => $this->app->configPath('settings.php'),
        ], 'settings-config');

        $this->publishes($this->migrationPublishes($migrationPaths), 'settings-migrations');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/settings.php', 'settings');

        $this->app->singleton(SettingCaster::class);
        $this->app->singleton(SettingsManager::class);

        $this->app->alias(SettingsManager::class, 'settings.manager');
    }

    private function migrationPathWithTimestamp(string $sourcePath, int $timestamp): string
    {
        $fileName = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($sourcePath));

        return $this->app->databasePath('migrations/'.date('Y_m_d_His', $timestamp).'_'.$fileName);
    }

    /** @param array<int, string> $migrationPaths */
    private function migrationPublishes(array $migrationPaths): array
    {
        sort($migrationPaths, SORT_STRING);

        $baseTimestamp = time();
        $publishes = [];

        foreach ($migrationPaths as $index => $migrationPath) {
            $publishes[$migrationPath] = $this->migrationPathWithTimestamp(
                $migrationPath,
                $baseTimestamp + $index
            );
        }

        return $publishes;
    }
}
