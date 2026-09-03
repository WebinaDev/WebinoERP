<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformEnvVar extends Model
{
    protected $table = 'platform_env_vars';

    protected $fillable = [
        'resource_id',
        'key',
        'value',
        'is_secret',
        'is_buildtime',
        'is_runtime',
        'is_preview',
    ];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'is_buildtime' => 'boolean',
            'is_runtime' => 'boolean',
            'is_preview' => 'boolean'
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
