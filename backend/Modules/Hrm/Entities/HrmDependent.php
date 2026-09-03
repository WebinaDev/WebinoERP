<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmDependent extends Model
{
    protected $table = 'hrm_dependents';

    protected $fillable = ['employee_id', 'full_name', 'relation', 'national_id', 'birth_date'];

    protected $casts = ['birth_date' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
