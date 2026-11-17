<?php

namespace Modules\Authz\Entities;

use Illuminate\Database\Eloquent\Model;

class AuthzFieldPolicy extends Model
{
    protected $table = 'authz_field_policies';

    protected $fillable = [
        'resource',
        'field',
        'permission_needed',
        'effect',
    ];
}
