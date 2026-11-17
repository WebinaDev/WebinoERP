<?php

namespace Modules\Authz\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Entities\CrmCompany;
use Modules\Org\Entities\OrgBranch;

class AuthzMembership extends Model
{
    protected $table = 'authz_memberships';

    protected $fillable = [
        'user_id',
        'company_id',
        'branch_id',
        'status',
        'is_default_context',
    ];

    protected $casts = [
        'is_default_context' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(OrgBranch::class, 'branch_id');
    }
}
