<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

use InvalidArgumentException;

final class FloatCast extends AbstractCast
{
    public function get(mixed $value): float
    {
        return (float) $value;
    }

    public function set(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $float = (float) $value;

        // NAN and INF have no string form that casts back, so storing them would read
        // as 0.0 later. Fail at the write instead of corrupting the value silently.
        if (! is_finite($float)) {
            throw new InvalidArgumentException('A float setting must be a finite number.');
        }

        // var_export honours serialize_precision (-1 by default), which emits the
        // shortest string that casts back to the identical float. A plain (string)
        // cast honours `precision` instead and truncates to 14 significant digits.
        return var_export($float, true);
    }
}
