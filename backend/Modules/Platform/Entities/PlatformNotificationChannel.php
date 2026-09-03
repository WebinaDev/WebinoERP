<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformNotificationChannel extends Model
{
    protected $table = 'platform_notification_channels';

    protected $fillable = [
        'name',
        'type',
        'config',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean'
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (in_array('uuid', $model->getFillable(), true) && empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
