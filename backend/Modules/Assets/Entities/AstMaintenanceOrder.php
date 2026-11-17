<?php

namespace Modules\Assets\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AstMaintenanceOrder extends Model
{
    protected $table = 'ast_maintenance_orders';

    protected $fillable = [
        'order_number',
        'asset_id',
        'plan_id',
        'type',
        'priority',
        'status',
        'scheduled_date',
        'completed_at',
        'description',
        'work_done',
        'labor_cost',
        'parts_cost',
        'total_cost',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_at' => 'datetime',
        'labor_cost' => 'decimal:2',
        'parts_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AstAsset::class, 'asset_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AstMaintenancePlan::class, 'plan_id');
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
