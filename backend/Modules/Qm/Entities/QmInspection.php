<?php

namespace Modules\Qm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Entities\AccProduct;

class QmInspection extends Model
{
    protected $table = 'qm_inspections';

    protected $fillable = [
        'inspection_number',
        'plan_id',
        'product_id',
        'lot_id',
        'inspection_type',
        'reference_id',
        'sample_size',
        'results',
        'verdict',
        'inspector_id',
        'inspected_at',
        'remarks',
    ];

    protected $casts = [
        'sample_size' => 'decimal:2',
        'results' => 'array',
        'inspected_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(QmInspectionPlan::class, 'plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AccProduct::class, 'product_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(QmLotBatch::class, 'lot_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
