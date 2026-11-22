<?php

namespace Modules\Hrm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmBulkDecreeBatch extends Model
{
    protected $table = 'hrm_bulk_decree_batches';

    protected $fillable = [
        'batch_no',
        'title',
        'decree_type',
        'effective_from',
        'percentage_increase',
        'fixed_amount_increase',
        'status',
        'total_employees',
        'created_by',
    ];

    protected $casts = [
        'percentage_increase' => 'decimal:2',
        'fixed_amount_increase' => 'decimal:2',
        'effective_from' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
