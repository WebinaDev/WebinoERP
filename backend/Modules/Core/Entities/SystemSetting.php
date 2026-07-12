<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'key';

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * Get setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::find($key);
        return $setting ? $setting->value : $default;
    }

    /**
     * Set setting value
     */
    public static function set(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }
}

