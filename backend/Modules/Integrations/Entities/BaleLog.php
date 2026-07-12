<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class BaleLog extends Model
{
    protected $fillable = ['level', 'log_type', 'context'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
