<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformServiceTemplate extends Model
{
    protected $table = 'platform_service_templates';

    protected $fillable = [
        'slug',
        'name',
        'category',
        'description',
        'compose',
        'meta',
    ];

    protected function casts(): array
    {
        return [
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
