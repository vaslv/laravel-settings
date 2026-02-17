<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Vaslv\LaravelSettings\Casts\BooleanCast;
use Vaslv\LaravelSettings\Casts\FloatCast;
use Vaslv\LaravelSettings\Casts\HtmlCast;
use Vaslv\LaravelSettings\Casts\IntegerCast;
use Vaslv\LaravelSettings\Casts\JsonCast;
use Vaslv\LaravelSettings\Casts\MarkdownCast;
use Vaslv\LaravelSettings\Casts\SettingCast;
use Vaslv\LaravelSettings\Casts\StringCast;

final class SettingCaster
{
    private Container $container;

    /** @var array<string, class-string<SettingCast>> */
    private array $map = [
        SettingType::STRING->value => StringCast::class,
        SettingType::BOOLEAN->value => BooleanCast::class,
        SettingType::INTEGER->value => IntegerCast::class,
        SettingType::FLOAT->value => FloatCast::class,
        SettingType::HTML->value => HtmlCast::class,
        SettingType::JSON->value => JsonCast::class,
        SettingType::MARKDOWN->value => MarkdownCast::class,
    ];

    /** @param array<string, class-string<SettingCast>> $map */
    public function __construct(Container $container, array $map = [])
    {
        $this->container = $container;

        if ($map !== []) {
            $this->map = $map + $this->map;
        }
    }

    public function has(string $type): bool
    {
        return isset($this->map[$type]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function resolve(string $type): SettingCast
    {
        if (! isset($this->map[$type])) {
            throw new InvalidArgumentException("Unknown setting type: {$type}");
        }

        return $this->container->make($this->map[$type]);
    }
}
