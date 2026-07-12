<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreInfraAuditLog extends Model
{
    protected $table = 'core_infra_audit_logs';

    protected $fillable = [
        'user_id',
        'channel',
        'action',
        'subject_type',
        'subject_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
