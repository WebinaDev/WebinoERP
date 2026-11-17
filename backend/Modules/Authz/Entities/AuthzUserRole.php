<?php

namespace Modules\Authz\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Entities\CrmCompany;

class AuthzUserRole extends Model
{
    protected $table = 'authz_user_roles';

    protected $fillable = [
        'user_id',
        'company_id',
        'role_id',
        'scope_json',
    ];

    protected $casts = [
        'scope_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AuthzRole::class, 'role_id');
    }
}
