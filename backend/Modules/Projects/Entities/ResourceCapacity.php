<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;

class ResourceCapacity extends Model
{
    protected $table = 'prj_resource_capacities';

    protected $fillable = [
        'user_id', 'weekday', 'hours',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
        ];
    }
}
