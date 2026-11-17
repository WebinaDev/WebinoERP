<?php

namespace Modules\Assets\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Entities\CrmCompany;
use Modules\Hrm\Entities\HrmEmployee;

class AstAsset extends Model
{
    protected $table = 'ast_assets';

    protected $fillable = [
        'asset_code',
        'name',
        'category',
        'company_id',
        'branch_id',
        'location',
        'custodian_id',
        'purchase_date',
        'purchase_cost',
        'salvage_value',
        'useful_life_months',
        'depreciation_method',
        'accumulated_depreciation',
        'book_value',
        'status',
        'asset_account_id',
        'depreciation_account_id',
        'accum_depr_account_id',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'useful_life_months' => 'integer',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'custodian_id');
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(AstMaintenancePlan::class, 'asset_id');
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(AstMaintenanceOrder::class, 'asset_id');
    }

    public function insurances(): HasMany
    {
        return $this->hasMany(AstInsurance::class, 'asset_id');
    }
}
