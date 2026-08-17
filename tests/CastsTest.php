<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Tests;

use InvalidArgumentException;
use JsonException;
use Vaslv\LaravelSettings\Facades\Settings;
use Vaslv\LaravelSettings\Models\Setting;

final class CastsTest extends TestCase
{
    public function test_boolean_round_trips_both_states(): void
    {
        Settings::set('flag.on', true, 'boolean');
        Settings::set('flag.off', false, 'boolean');

        $this->assertTrue(Settings::get('flag.on'));
        $this->assertFalse(Settings::get('flag.off'));
        $this->assertSame('1', Setting::query()->where('key', 'flag.on')->value('value'));
        $this->assertSame('0', Setting::query()->where('key', 'flag.off')->value('value'));
    }

    public function test_boolean_writes_agree_with_boolean_reads(): void
    {
        foreach (['false', 'off', 'no', '0', 0, false] as $falsy) {
            Settings::set('flag.value', $falsy, 'boolean');
            $this->assertFalse(Settings::get('flag.value'), var_export($falsy, true).' should store as false');
        }

        foreach (['true', 'on', 'yes', '1', 1, true] as $truthy) {
            Settings::set('flag.value', $truthy, 'boolean');
            $this->assertTrue(Settings::get('flag.value'), var_export($truthy, true).' should store as true');
        }
    }

    public function test_every_type_round_trips_null(): void
    {
        foreach (['string', 'boolean', 'integer', 'float', 'html', 'markdown'] as $type) {
            Settings::set("nullable.{$type}", null, $type);

            $this->assertNull(Settings::get("nullable.{$type}"), "{$type} should round-trip null");
            $this->assertNull(Setting::query()->where('key', "nullable.{$type}")->value('value'));
        }
    }

    public function test_float_keeps_full_precision_through_a_round_trip(): void
    {
        $precise = 1.23456789012345678;

        Settings::set('num.precise', $precise, 'float');
        Settings::set('num.big', 1.0e25, 'float');

        // A (string) cast honours `precision` (14 digits) and used to truncate this
        // to 1.2345678901235 on the way into the database.
        $this->assertSame($precise, Settings::get('num.precise'));
        $this->assertSame(1.0e25, Settings::get('num.big'));
    }

    public function test_float_rejects_values_with_no_round_trippable_form(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('finite number');

        Settings::set('num.broken', INF, 'float');
    }

    public function test_float_round_trips(): void
    {
        Settings::set('num.rate', 1.5, 'float');
        Settings::set('num.zero', 0.0, 'float');
        Settings::set('num.negative', -2.25, 'float');

        $this->assertSame(1.5, Settings::get('num.rate'));
        $this->assertSame(0.0, Settings::get('num.zero'));
        $this->assertSame(-2.25, Settings::get('num.negative'));
    }

    public function test_html_and_markdown_are_stored_verbatim(): void
    {
        $html = '<p class="lead">Hello &amp; welcome</p>';
        $markdown = "# Title\n\n* one\n* two";

        Settings::set('page.body', $html, 'html');
        Settings::set('page.notes', $markdown, 'markdown');

        // Both types are raw string passthrough. They do not escape or sanitize;
        // the consuming application owns that decision at render time.
        $this->assertSame($html, Settings::get('page.body'));
        $this->assertSame($markdown, Settings::get('page.notes'));
    }

    public function test_integer_round_trips_including_zero(): void
    {
        Settings::set('num.retries', 5, 'integer');
        Settings::set('num.none', 0, 'integer');
        Settings::set('num.below', -3, 'integer');

        $this->assertSame(5, Settings::get('num.retries'));
        $this->assertSame(0, Settings::get('num.none'));
        $this->assertSame(-3, Settings::get('num.below'));
    }

    public function test_json_keeps_null_distinct_from_an_absent_value(): void
    {
        Settings::set('cfg.jsonNull', null, 'json');

        // In JSON, null is a value. It is encoded as "null" rather than left as a SQL
        // NULL, so it stays distinguishable from a column that was never written.
        $this->assertSame('null', Setting::query()->where('key', 'cfg.jsonNull')->value('value'));
        $this->assertNull(Settings::get('cfg.jsonNull'));
    }

    public function test_json_round_trips_nested_structures(): void
    {
        $payload = ['a' => 1, 'b' => ['c' => true, 'd' => [1, 2, 3]]];

        Settings::set('cfg.tree', $payload, 'json');

        $this->assertSame($payload, Settings::get('cfg.tree'));
    }

    public function test_json_scalar_zero_is_not_swallowed_into_an_empty_array(): void
    {
        Settings::set('cfg.zero', 0, 'json');
        Settings::set('cfg.false', false, 'json');
        Settings::set('cfg.emptyString', '', 'json');
        Settings::set('cfg.emptyArray', [], 'json');

        // json_encode(0) is the string "0", which is falsy in PHP. A truthiness check
        // in JsonCast::get() turned this into [] and silently lost the value.
        $this->assertSame(0, Settings::get('cfg.zero'));
        $this->assertFalse(Settings::get('cfg.false'));
        $this->assertSame('', Settings::get('cfg.emptyString'));
        $this->assertSame([], Settings::get('cfg.emptyArray'));
    }

    public function test_json_surfaces_malformed_payloads_instead_of_guessing(): void
    {
        Setting::query()->create([
            'key' => 'cfg.broken',
            'group' => 'cfg',
            'type' => 'json',
            'encrypted' => false,
            'value' => '{not json',
        ]);

        $this->expectException(JsonException::class);
        Settings::get('cfg.broken');
    }

    public function test_string_round_trips(): void
    {
        Settings::set('site.title', 'Laravel Settings', 'string');

        $this->assertSame('Laravel Settings', Settings::get('site.title'));
    }

    public function test_type_is_inferred_from_the_php_value_when_not_given(): void
    {
        Settings::set('inferred.bool', true);
        Settings::set('inferred.int', 42);
        Settings::set('inferred.float', 1.5);
        Settings::set('inferred.array', ['a' => 1]);
        Settings::set('inferred.string', 'text');

        $this->assertSame('boolean', Setting::query()->where('key', 'inferred.bool')->value('type'));
        $this->assertSame('integer', Setting::query()->where('key', 'inferred.int')->value('type'));
        $this->assertSame('float', Setting::query()->where('key', 'inferred.float')->value('type'));
        $this->assertSame('json', Setting::query()->where('key', 'inferred.array')->value('type'));
        $this->assertSame('string', Setting::query()->where('key', 'inferred.string')->value('type'));
    }

    public function test_type_is_kept_when_an_existing_key_is_overwritten_without_one(): void
    {
        Settings::set('num.count', 7, 'integer');
        Settings::set('num.count', '9');

        $this->assertSame('integer', Setting::query()->where('key', 'num.count')->value('type'));
        $this->assertSame(9, Settings::get('num.count'));
    }
}
