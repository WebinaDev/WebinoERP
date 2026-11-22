<?php

namespace Modules\Hrm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmDataAccessAuditLog extends Model
{
    protected $table = 'hrm_data_access_audit_logs';

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'access_type',
        'ip_address',
        'user_agent',
        'accessed_fields',
    ];

    protected $casts = [
        'accessed_fields' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
