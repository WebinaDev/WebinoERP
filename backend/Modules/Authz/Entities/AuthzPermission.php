<?php

namespace Modules\Authz\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuthzPermission extends Model
{
    protected $table = 'authz_permissions';

    protected $fillable = [
        'code',
        'module',
        'resource',
        'action',
        'title_fa',
        'title_en',
        'description',
        'description_en',
        'risk_level',
        'requires_reason',
        'requires_mfa',
    ];

    protected $casts = [
        'requires_reason' => 'boolean',
        'requires_mfa' => 'boolean',
    ];

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(AuthzRolePermission::class, 'permission_id');
    }
}
