<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings;

use Illuminate\Support\ServiceProvider;

final class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only load the bundled migrations when the application has not published its
        // own copies. Loading both makes `migrate` run two files that each call
        // Schema::create() for the same table, which fails with "table already exists".
        if (! $this->migrationsArePublished()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // publishes() is only ever consumed by vendor:publish, so keep the glob and the
        // path bookkeeping out of the hot path for web and queue requests.
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/settings.php' => $this->app->configPath('settings.php'),
        ], 'settings-config');

        $this->publishes($this->migrationPublishes($this->packageMigrationPaths()), 'settings-migrations');
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

    /**
     * @param  array<int, string>  $migrationPaths
     * @return array<string, string>
     */
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

    /**
     * True when the application already owns a copy of any bundled migration, matched
     * on the timestamp-stripped file name that migrationPathWithTimestamp() writes.
     */
    private function migrationsArePublished(): bool
    {
        $appMigrations = glob($this->app->databasePath('migrations/*.php')) ?: [];

        if ($appMigrations === []) {
            return false;
        }

        $published = array_map(
            fn (string $path): string => $this->strippedMigrationName($path),
            $appMigrations
        );

        foreach ($this->packageMigrationPaths() as $packagePath) {
            if (in_array($this->strippedMigrationName($packagePath), $published, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    private function packageMigrationPaths(): array
    {
        return glob(__DIR__.'/../database/migrations/*.php') ?: [];
    }

    private function strippedMigrationName(string $path): string
    {
        return (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($path));
    }
}
