<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;

class PrjApproval extends Model
{
    protected $table = 'prj_approvals';

    protected $fillable = [
        'project_id', 'customer_account_id', 'title', 'note', 'status',
        'decision_note', 'created_by', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }
}
