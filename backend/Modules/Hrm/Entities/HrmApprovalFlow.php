<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmApprovalFlow extends Model
{
    protected $table = 'hrm_approval_flows';

    protected $fillable = ['request_type', 'name', 'steps', 'is_active'];

    protected $casts = [
        'steps' => 'array',
        'is_active' => 'boolean',
    ];
}
