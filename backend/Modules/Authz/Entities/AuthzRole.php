<?php

namespace Modules\Authz\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Entities\CrmCompany;

class AuthzRole extends Model
{
    protected $table = 'authz_roles';

    protected $fillable = [
        'code',
        'title_fa',
        'company_id',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(AuthzRolePermission::class, 'role_id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(AuthzUserRole::class, 'role_id');
    }
}
