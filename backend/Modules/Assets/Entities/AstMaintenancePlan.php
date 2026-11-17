<?php

namespace Modules\Assets\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AstMaintenancePlan extends Model
{
    protected $table = 'ast_maintenance_plans';

    protected $fillable = [
        'asset_id',
        'title',
        'frequency',
        'interval_days',
        'last_performed_at',
        'next_due_date',
        'checklist',
        'is_active',
    ];

    protected $casts = [
        'interval_days' => 'integer',
        'last_performed_at' => 'date',
        'next_due_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AstAsset::class, 'asset_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(AstMaintenanceOrder::class, 'plan_id');
    }
}
