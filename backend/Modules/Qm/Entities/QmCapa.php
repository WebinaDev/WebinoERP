<?php

namespace Modules\Qm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QmCapa extends Model
{
    protected $table = 'qm_capas';

    protected $fillable = [
        'capa_number',
        'ncr_id',
        'type',
        'root_cause',
        'action_plan',
        'verification_result',
        'status',
        'target_date',
        'owner_id',
        'created_by',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(QmNcr::class, 'ncr_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
