<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Vaslv\LaravelSettings\SettingsManager;

/**
 * @property int $id
 * @property string $key
 * @property string|null $group
 * @property string $type
 * @property bool $encrypted
 * @property string|null $value
 */
final class Setting extends Model
{
    // Property form, not the casts() method: the method was added in Laravel 11 and
    // this package still supports Laravel 10.
    protected $casts = [
        'encrypted' => 'boolean',
    ];

    protected $fillable = [
        'key',
        'group',
        'type',
        'encrypted',
        'value',
    ];

    public function getTable(): string
    {
        return (string) Config::get('settings.table', 'settings');
    }

    /**
     * @throws BindingResolutionException
     */
    public function getValue(): mixed
    {
        /** @var SettingsManager $manager */
        $manager = App::make(SettingsManager::class);

        return $manager->castValue(
            $this->type,
            $this->attributes['value'] ?? null,
            (bool) ($this->attributes['encrypted'] ?? false)
        );
    }

    protected static function booted(): void
    {
        self::saved(fn () => self::clearSettingsCache());
        self::deleted(fn () => self::clearSettingsCache());
    }

    private static function clearSettingsCache(): void
    {
        /** @var SettingsManager $manager */
        $manager = App::make(SettingsManager::class);
        $manager->clearCache();
    }
}
