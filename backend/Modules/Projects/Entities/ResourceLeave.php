<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;

class ResourceLeave extends Model
{
    protected $table = 'prj_leave_entries';

    protected $fillable = [
        'user_id', 'starts_on', 'ends_on', 'kind', 'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
