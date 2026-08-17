<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

final class BooleanCast extends AbstractCast
{
    public function get(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function set(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // The same filter get() reads with, so a write and a read of the same input
        // agree. A plain truthiness test stored the string "false" as true, which is
        // exactly what an unconverted checkbox or form field hands you.
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
    }
}
