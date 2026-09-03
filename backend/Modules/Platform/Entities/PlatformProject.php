<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformProject extends Model
{
    protected $table = 'platform_projects';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'crm_account_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array'
        ];
    }

    public function environments()
    {
        return $this->hasMany(PlatformEnvironment::class, 'project_id');
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
