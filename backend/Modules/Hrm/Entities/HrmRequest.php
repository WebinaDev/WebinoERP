<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmRequest extends Model
{
    protected $table = 'hrm_requests';

    protected $fillable = [
        'employee_id', 'user_id', 'type', 'status', 'payload', 'notes', 'reviewed_by',
    ];

    protected $casts = ['payload' => 'array'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
