<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use InvalidArgumentException;
use Vaslv\LaravelSettings\Casts\AbstractCast;
use Vaslv\LaravelSettings\Casts\StringCast;
use Vaslv\LaravelSettings\Facades\Settings;
use Vaslv\LaravelSettings\Models\Setting;
use Vaslv\LaravelSettings\SettingCaster;
use Vaslv\LaravelSettings\SettingType;

final class SettingCasterTest extends TestCase
{
    public function test_a_custom_map_adds_types_without_dropping_the_built_ins(): void
    {
        $caster = new SettingCaster($this->app, ['reversed' => ReversedCast::class]);

        $this->assertTrue($caster->has('reversed'));
        $this->assertTrue($caster->has(SettingType::STRING->value));
        $this->assertInstanceOf(ReversedCast::class, $caster->resolve('reversed'));
        $this->assertInstanceOf(StringCast::class, $caster->resolve(SettingType::STRING->value));
    }

    public function test_a_custom_map_can_override_a_built_in_type(): void
    {
        $caster = new SettingCaster($this->app, [SettingType::STRING->value => ReversedCast::class]);

        $this->assertInstanceOf(ReversedCast::class, $caster->resolve(SettingType::STRING->value));
    }

    public function test_every_enum_case_resolves_to_a_cast(): void
    {
        $caster = $this->app->make(SettingCaster::class);

        foreach (SettingType::cases() as $case) {
            $this->assertTrue($caster->has($case->value), "No cast registered for {$case->value}");
            $this->assertNotNull($caster->resolve($case->value));
        }
    }

    public function test_resolving_an_unknown_type_throws(): void
    {
        $caster = $this->app->make(SettingCaster::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown setting type: nope');

        $caster->resolve('nope');
    }

    public function test_the_manager_returns_unknown_types_as_raw_strings(): void
    {
        Setting::query()->create([
            'key' => 'weird.one',
            'group' => 'weird',
            'type' => 'not-a-real-type',
            'encrypted' => false,
            'value' => 'raw-payload',
        ]);

        // has() is checked before resolve(), so an unrecognised type degrades to the
        // stored string rather than throwing. A typo in a type name is therefore silent.
        $this->assertSame('raw-payload', Settings::get('weird.one'));
    }
}

final class ReversedCast extends AbstractCast
{
    public function get(mixed $value): ?string
    {
        return $value === null ? null : strrev((string) $value);
    }

    public function set(mixed $value): string
    {
        return strrev((string) $value);
    }
}
