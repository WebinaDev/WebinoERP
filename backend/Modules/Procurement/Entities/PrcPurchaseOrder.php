<?php

namespace Modules\Procurement\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Entities\AccPerson;
use Modules\Crm\Entities\CrmCompany;
use Modules\Org\Entities\OrgBranch;

class PrcPurchaseOrder extends Model
{
    protected $table = 'prc_purchase_orders';

    protected $fillable = [
        'po_number',
        'company_id',
        'branch_id',
        'vendor_id',
        'pr_id',
        'rfq_id',
        'order_date',
        'expected_delivery_date',
        'status',
        'payment_terms',
        'items',
        'subtotal',
        'tax',
        'total',
        'created_by',
        'confirmed_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(AccPerson::class, 'vendor_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PrcPurchaseRequest::class, 'pr_id');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(PrcGoodsReceipt::class, 'po_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(OrgBranch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
