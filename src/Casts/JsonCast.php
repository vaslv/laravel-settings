<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

use JsonException;

final class JsonCast extends AbstractCast
{
    /**
     * @throws JsonException
     */
    public function get(mixed $value): mixed
    {
        // Explicit null/empty check, not truthiness: the encoded JSON scalar "0" is a
        // falsy string, so a truthiness test decodes the stored value 0 as [].
        if ($value === null || $value === '') {
            return [];
        }

        return json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Null is encoded rather than passed through: in JSON it is a value in its own
     * right, so "null" round-trips back to null and stays distinct from a SQL NULL.
     *
     * @throws JsonException
     */
    public function set(mixed $value): ?string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
