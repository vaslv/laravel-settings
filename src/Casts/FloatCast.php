<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

final class FloatCast extends AbstractCast
{
    public function get(mixed $value): float
    {
        return (float) $value;
    }

    public function set(mixed $value): string
    {
        return (string) ((float) $value);
    }
}
