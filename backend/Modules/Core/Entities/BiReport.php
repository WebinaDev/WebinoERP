<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class BiReport extends Model
{
    protected $table = 'core_bi_reports';

    protected $fillable = [
        'name', 'source', 'columns', 'filters', 'layout',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'filters' => 'array',
            'layout' => 'array',
        ];
    }
}
