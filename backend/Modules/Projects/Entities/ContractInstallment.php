<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractInstallment extends Model
{
    protected $table = 'prj_contract_installments';

    protected $fillable = [
        'contract_id', 'amount', 'due_date', 'paid_at', 'payment_ref',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }
}
