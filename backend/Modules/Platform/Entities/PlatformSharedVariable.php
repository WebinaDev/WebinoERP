<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformSharedVariable extends Model
{
    protected $table = 'platform_shared_variables';

    protected $fillable = [
        'key',
        'value',
        'is_secret',
        'project_id',
    ];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean'
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
