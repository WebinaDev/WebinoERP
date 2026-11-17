<?php

namespace Modules\Qm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Entities\AccProduct;

class QmInspectionPlan extends Model
{
    protected $table = 'qm_inspection_plans';

    protected $fillable = [
        'title',
        'inspection_type',
        'product_id',
        'criteria',
        'instructions',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(AccProduct::class, 'product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
