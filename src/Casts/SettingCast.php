<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

interface SettingCast
{
    public function get(mixed $value): mixed;

    public function set(mixed $value): string;
}
