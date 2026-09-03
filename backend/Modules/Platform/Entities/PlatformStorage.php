<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformStorage extends Model
{
    protected $table = 'platform_storages';

    protected $fillable = [
        'uuid',
        'name',
        'driver',
        'endpoint',
        'bucket',
        'region',
        'access_key',
        'secret_key',
        'path_style',
    ];

    protected function casts(): array
    {
        return [
            'access_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'path_style' => 'boolean'
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
