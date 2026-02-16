<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

class StringCast extends AbstractCast
{
    public function get(mixed $value): ?string
    {
        return $this->normalizeNull($value);
    }

    public function set(mixed $value): string
    {
        return $this->normalizeNull($value) ?? '';
    }
}
