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
        return $value ? json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR) : [];
    }

    /**
     * @throws JsonException
     */
    public function set(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
