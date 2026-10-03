<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class BiReport extends Model
{
    protected $table = 'core_bi_reports';

    protected $fillable = [
        'name', 'source', 'columns', 'filters', 'layout', 'joins', 'schedule', 'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'filters' => 'array',
            'layout' => 'array',
            'joins' => 'array',
            'schedule' => 'array',
            'last_sent_at' => 'datetime',
        ];
    }
}
