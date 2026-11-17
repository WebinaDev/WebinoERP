<?php

namespace Modules\DeviceGateway\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Entities\CrmCompany;
use Modules\Org\Entities\OrgBranch;
use Modules\Scm\Entities\ScmWarehouse;

class DevGateway extends Model
{
    protected $table = 'dev_gateways';

    protected $fillable = [
        'gateway_key',
        'name',
        'os_platform',
        'company_id',
        'branch_id',
        'warehouse_id',
        'ip_address',
        'status',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(OrgBranch::class, 'branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ScmWarehouse::class, 'warehouse_id');
    }

    public function peripherals(): HasMany
    {
        return $this->hasMany(DevPeripheral::class, 'gateway_id');
    }
}
