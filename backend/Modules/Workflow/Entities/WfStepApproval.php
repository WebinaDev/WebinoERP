<?php

namespace Modules\Workflow\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WfStepApproval extends Model
{
    protected $table = 'wf_step_approvals';

    protected $fillable = [
        'instance_id',
        'step_order',
        'target_role',
        'target_user_id',
        'status',
        'sla_due_at',
        'decided_by',
        'decided_at',
        'comment',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'sla_due_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WfInstance::class, 'instance_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
