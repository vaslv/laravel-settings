<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Vaslv\LaravelSettings\SettingsManager;

if (! function_exists('setting')) {
    function setting(string $key, mixed $value = null): mixed
    {
        /** @var SettingsManager $manager */
        $manager = App::make(SettingsManager::class);

        if (func_num_args() === 1) {
            return $manager->get($key);
        }

        $manager->set($key, $value);

        return $value;
    }
}
