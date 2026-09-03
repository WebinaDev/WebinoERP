<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformSshKey extends Model
{
    protected $table = 'platform_ssh_keys';

    protected $fillable = [
        'name',
        'fingerprint',
        'public_key',
        'private_key',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted'
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
