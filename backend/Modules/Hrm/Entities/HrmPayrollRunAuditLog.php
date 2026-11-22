<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmPayrollRunAuditLog extends Model
{
    protected $table = 'hrm_payroll_run_audit_logs';

    protected $fillable = [
        'payroll_run_id',
        'from_state',
        'to_state',
        'performed_by',
        'action_note',
        'state_snapshot',
    ];

    protected $casts = [
        'state_snapshot' => 'array',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(HrmPayrollRun::class, 'payroll_run_id');
    }
}
