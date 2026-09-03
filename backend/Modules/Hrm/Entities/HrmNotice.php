<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmNotice extends Model
{
    protected $table = 'hrm_notices';

    protected $fillable = ['title', 'body', 'date_from', 'date_to', 'is_active'];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'is_active' => 'boolean',
    ];
}
