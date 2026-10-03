<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmQuoteApproval extends Model
{
    protected $table = 'crm_quote_approvals';

    protected $fillable = [
        'deal_id', 'status', 'discount_percent', 'total', 'threshold_percent',
        'requested_by', 'decided_by', 'reason', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }
}
