<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

final class IntegerCast extends AbstractCast
{
    public function get(mixed $value): int
    {
        return (int) $value;
    }

    public function set(mixed $value): string
    {
        return (string) ((int) $value);
    }
}
