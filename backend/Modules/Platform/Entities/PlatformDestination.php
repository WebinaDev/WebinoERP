<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformDestination extends Model
{
    protected $table = 'platform_destinations';

    protected $fillable = [
        'uuid',
        'server_id',
        'name',
        'network_name',
        'driver',
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
