<?php

namespace Modules\Qm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Entities\AccProduct;

class QmNcr extends Model
{
    protected $table = 'qm_ncrs';

    protected $fillable = [
        'ncr_number',
        'inspection_id',
        'lot_id',
        'product_id',
        'severity',
        'status',
        'defect_description',
        'disposition',
        'affected_quantity',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'affected_quantity' => 'decimal:4',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(QmInspection::class, 'inspection_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(QmLotBatch::class, 'lot_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AccProduct::class, 'product_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
