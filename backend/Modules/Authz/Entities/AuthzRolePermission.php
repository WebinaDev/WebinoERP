<?php

namespace Modules\Authz\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthzRolePermission extends Model
{
    protected $table = 'authz_role_permissions';

    protected $fillable = [
        'role_id',
        'permission_id',
        'scope',
        'conditions_json',
        'effect',
    ];

    protected $casts = [
        'conditions_json' => 'array',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(AuthzRole::class, 'role_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(AuthzPermission::class, 'permission_id');
    }
}
