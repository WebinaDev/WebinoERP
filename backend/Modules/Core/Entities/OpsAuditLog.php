<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class OpsAuditLog extends Model
{
    protected $table = 'ops_audit_logs';

    protected $fillable = [
        'module', 'action', 'subject_type', 'subject_id', 'user_id', 'changes',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }
}
