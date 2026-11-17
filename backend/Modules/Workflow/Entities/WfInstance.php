<?php

namespace Modules\Workflow\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WfInstance extends Model
{
    protected $table = 'wf_instances';

    protected $fillable = [
        'definition_id',
        'entity_type',
        'entity_id',
        'title',
        'amount',
        'status',
        'current_step',
        'context',
        'initiated_by',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'current_step' => 'integer',
        'context' => 'array',
        'completed_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WfDefinition::class, 'definition_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function stepApprovals(): HasMany
    {
        return $this->hasMany(WfStepApproval::class, 'instance_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(WfAuditLog::class, 'instance_id');
    }
}
