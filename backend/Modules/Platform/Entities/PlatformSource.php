<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformSource extends Model
{
    protected $table = 'platform_sources';

    protected $fillable = [
        'uuid',
        'name',
        'provider',
        'base_url',
        'token',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'meta' => 'array'
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
