<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmShiftRotation extends Model
{
    protected $table = 'hrm_shift_rotations';

    protected $fillable = ['name', 'cycle_length', 'pattern', 'is_active'];

    protected $casts = [
        'pattern' => 'array',
        'is_active' => 'boolean',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(HrmShiftAssignment::class, 'rotation_id');
    }
}
