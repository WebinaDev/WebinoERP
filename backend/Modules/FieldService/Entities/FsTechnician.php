<?php

namespace Modules\FieldService\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrm\Entities\HrmEmployee;

class FsTechnician extends Model
{
    protected $table = 'fs_technicians';

    protected $fillable = [
        'user_id',
        'employee_id',
        'skills',
        'zone',
        'phone',
        'is_available',
    ];

    protected $casts = [
        'skills' => 'array',
        'is_available' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
