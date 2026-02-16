<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Facades;

use Illuminate\Support\Facades\Facade;

final class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'settings.manager';
    }
}
