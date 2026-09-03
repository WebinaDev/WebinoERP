<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformDomain extends Model
{
    protected $table = 'platform_domains';

    protected $fillable = [
        'resource_id',
        'domain',
        'force_https',
        'hsts',
        'ssl_status',
        'redirect_to',
    ];

    protected function casts(): array
    {
        return [
            'force_https' => 'boolean',
            'hsts' => 'boolean'
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
