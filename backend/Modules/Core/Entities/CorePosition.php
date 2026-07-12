<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class CorePosition extends Model
{
    protected $table = 'core_positions';

    protected $fillable = ['title', 'permissions'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }
}
