<?php

namespace Modules\Authz\Entities;

use Illuminate\Database\Eloquent\Model;

class AuthzPolicy extends Model
{
    protected $table = 'authz_policies';

    protected $fillable = [
        'name',
        'name_en',
        'target_permission',
        'target_resource',
        'condition_expression',
        'effect',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];
}
