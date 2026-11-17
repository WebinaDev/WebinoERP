<?php

namespace Modules\Procurement\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Scm\Entities\ScmWarehouse;

class PrcGoodsReceipt extends Model
{
    protected $table = 'prc_goods_receipts';

    protected $fillable = [
        'grn_number',
        'po_id',
        'warehouse_id',
        'receipt_date',
        'delivery_note_ref',
        'status',
        'items',
        'received_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'items' => 'array',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PrcPurchaseOrder::class, 'po_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ScmWarehouse::class, 'warehouse_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
