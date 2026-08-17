<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

interface SettingCast
{
    public function get(mixed $value): mixed;

    /**
     * Null is a valid return: the value column is nullable, so a cast is allowed to
     * say "no value" rather than being forced to invent an empty string.
     */
    public function set(mixed $value): ?string;
}
