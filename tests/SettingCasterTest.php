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

        // Writes reject unknown types, but reads stay permissive so a row written
        // before that rule, or by something outside this package, is still readable.
        $this->assertSame('raw-payload', Settings::get('weird.one'));
    }

    public function test_writing_a_registered_custom_type_is_accepted(): void
    {
        $this->app->singleton(SettingCaster::class, fn ($app): SettingCaster => new SettingCaster(
            $app,
            ['reversed' => ReversedCast::class]
        ));

        Settings::set('custom.word', 'stressed', 'reversed');

        $this->assertSame('desserts', Setting::query()->where('key', 'custom.word')->value('value'));
        $this->assertSame('stressed', Settings::get('custom.word'));
    }

    public function test_writing_an_unknown_type_is_rejected(): void
    {
        // A typo used to be stored happily and turned the value into a lossy string.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown setting type: boolena');

        Settings::set('flag.typo', true, 'boolena');
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
