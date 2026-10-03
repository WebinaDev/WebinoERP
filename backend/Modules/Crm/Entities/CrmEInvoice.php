<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmEInvoice extends Model
{
    protected $table = 'crm_einvoices';

    protected $fillable = [
        'company_id', 'deal_id', 'invoice_type', 'pattern', 'serial', 'taxid',
        'seller_economic_code', 'seller_national_id', 'buyer_economic_code', 'buyer_national_id', 'buyer_type',
        'currency_code', 'total', 'vat', 'status', 'document', 'provider_response',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'vat' => 'decimal:2',
            'document' => 'array',
            'provider_response' => 'array',
        ];
    }
}
