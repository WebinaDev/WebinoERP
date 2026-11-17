<?php

namespace Modules\Org\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Entities\CrmCompany;

class OrgBranch extends Model
{
    protected $table = 'org_branches';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'phone',
        'address',
        'manager_id',
        'default_warehouse_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
