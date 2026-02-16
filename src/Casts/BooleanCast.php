<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

final class BooleanCast extends AbstractCast
{
    public function get(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function set(mixed $value): string
    {
        return $value ? '1' : '0';
    }
}
