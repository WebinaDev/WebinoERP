<?php

namespace Modules\Workflow\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WfAuditLog extends Model
{
    protected $table = 'wf_audit_logs';

    protected $fillable = [
        'instance_id',
        'user_id',
        'action',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WfInstance::class, 'instance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
