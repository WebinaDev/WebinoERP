<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class ScimToken extends Model
{
    protected $table = 'core_scim_tokens';

    protected $fillable = [
        'name', 'token_hash',
    ];
}
