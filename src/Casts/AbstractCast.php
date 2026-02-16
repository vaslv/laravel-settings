<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Casts;

abstract class AbstractCast implements SettingCast
{
    protected function normalizeNull(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
