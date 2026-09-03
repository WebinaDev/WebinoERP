<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformVolume extends Model
{
    protected $table = 'platform_volumes';

    protected $fillable = [
        'resource_id',
        'name',
        'mount_path',
        'host_path',
        'is_file',
    ];

    protected function casts(): array
    {
        return [
            'is_file' => 'boolean'
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
