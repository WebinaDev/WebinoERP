<?php

namespace Modules\Authz\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Entities\CrmCompany;

class AuthzUserPermission extends Model
{
    protected $table = 'authz_user_permissions';

    protected $fillable = [
        'user_id',
        'company_id',
        'permission_id',
        'scope',
        'conditions_json',
        'effect',
    ];

    protected $casts = [
        'conditions_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(AuthzPermission::class, 'permission_id');
    }
}
