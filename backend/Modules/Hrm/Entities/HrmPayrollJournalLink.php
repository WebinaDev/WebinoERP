<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmPayrollJournalLink extends Model
{
    protected $table = 'hrm_payroll_journal_links';

    protected $fillable = [
        'payroll_run_id',
        'journal_entry_id',
        'voucher_segment',
        'total_debit',
        'total_credit',
        'status',
        'lines_detail',
    ];

    protected $casts = [
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'lines_detail' => 'array',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(HrmPayrollRun::class, 'payroll_run_id');
    }
}
