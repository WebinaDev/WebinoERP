<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmSource extends Model
{
    protected $table = 'crm_sources';

    protected $fillable = ['name', 'description', 'is_active', 'color', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
