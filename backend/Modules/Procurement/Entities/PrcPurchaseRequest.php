<?php

namespace Modules\Procurement\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Entities\CrmCompany;
use Modules\Org\Entities\OrgBranch;

class PrcPurchaseRequest extends Model
{
    protected $table = 'prc_purchase_requests';

    protected $fillable = [
        'pr_number',
        'company_id',
        'branch_id',
        'department',
        'priority',
        'status',
        'required_by_date',
        'reason',
        'items',
        'total_estimated_amount',
        'requested_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'required_by_date' => 'date',
        'items' => 'array',
        'total_estimated_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(OrgBranch::class, 'branch_id');
    }
}
