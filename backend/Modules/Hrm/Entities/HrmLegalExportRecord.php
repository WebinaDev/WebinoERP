<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLegalExportRecord extends Model
{
    protected $table = 'hrm_legal_export_records';

    protected $fillable = [
        'payroll_run_id',
        'export_type',
        'format',
        'file_path',
        'total_records',
        'total_amount',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(HrmPayrollRun::class, 'payroll_run_id');
    }
}
