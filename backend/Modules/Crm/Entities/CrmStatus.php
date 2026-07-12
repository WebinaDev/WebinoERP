<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmStatus extends Model
{
    protected $table = 'crm_statuses';

    protected $fillable = ['name', 'description', 'color', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
