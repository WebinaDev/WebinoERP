<?php

namespace Modules\Authz\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Entities\CrmCompany;

class AuthzAccessLog extends Model
{
    protected $table = 'authz_access_logs';

    protected $fillable = [
        'user_id',
        'company_id',
        'branch_id',
        'permission_code',
        'resource_type',
        'resource_id',
        'decision',
        'reason',
        'obligations',
        'ip',
    ];

    protected $casts = [
        'obligations' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }
}
