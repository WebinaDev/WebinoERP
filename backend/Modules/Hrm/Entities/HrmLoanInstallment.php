<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLoanInstallment extends Model
{
    protected $table = 'hrm_loan_installments';

    protected $fillable = ['loan_id', 'payroll_run_id', 'amount', 'paid_on', 'status'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_on' => 'date',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(HrmLoan::class, 'loan_id');
    }
}
