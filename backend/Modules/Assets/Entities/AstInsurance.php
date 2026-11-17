<?php

namespace Modules\Assets\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AstInsurance extends Model
{
    protected $table = 'ast_insurances';

    protected $fillable = [
        'asset_id',
        'policy_number',
        'insurer_name',
        'start_date',
        'end_date',
        'premium_amount',
        'coverage_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'premium_amount' => 'decimal:2',
        'coverage_amount' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AstAsset::class, 'asset_id');
    }
}
