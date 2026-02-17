<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Vaslv\LaravelSettings\SettingCaster;
use Vaslv\LaravelSettings\SettingsManager;

/**
 * @property int $id
 * @property string $key
 * @property string $group
 * @property string $type
 * @property mixed $value
 */
final class Setting extends Model
{
    protected $fillable = [
        'key',
        'group',
        'type',
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
        /** @var SettingCaster $caster */
        $caster = App::make(SettingCaster::class);

        if (! $caster->has($this->type)) {
            return $this->value;
        }

        return $caster->resolve($this->type)->get($this->attributes['value']);
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
