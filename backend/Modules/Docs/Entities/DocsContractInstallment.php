<?php

namespace Modules\Docs\Entities;

use Illuminate\Database\Eloquent\Model;

class DocsContractInstallment extends Model
{
    protected $table = 'docs_contract_installments';

    protected $fillable = [
        'contract_id', 'title', 'amount', 'due_date', 'status', 'sales_invoice_id', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];
}
