<?php

namespace Modules\Docs\Entities;

use Illuminate\Database\Eloquent\Model;

class DocsContractAddendum extends Model
{
    protected $table = 'docs_contract_addenda';

    protected $fillable = [
        'contract_id', 'addendum_number', 'title', 'type', 'amount_delta',
        'new_end_date', 'terms_and_body', 'status', 'effective_date',
    ];

    protected $casts = [
        'amount_delta' => 'decimal:2',
        'new_end_date' => 'date',
        'effective_date' => 'datetime',
    ];
}
