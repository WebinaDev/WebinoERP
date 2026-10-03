<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmLoan extends Model
{
    protected $table = 'hrm_loans';

    protected $fillable = [
        'employee_id', 'type', 'title', 'principal', 'installment_amount', 'remaining_balance',
        'installments_total', 'installments_paid', 'start_date', 'status', 'notes',
    ];

    protected $casts = [
        'principal' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'start_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(HrmLoanInstallment::class, 'loan_id');
    }
}
