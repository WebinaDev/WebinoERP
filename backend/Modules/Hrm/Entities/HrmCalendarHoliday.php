<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmCalendarHoliday extends Model
{
    protected $table = 'hrm_calendar_holidays';

    protected $fillable = [
        'holiday_date',
        'title',
        'holiday_type',
        'is_recurring_yearly',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_recurring_yearly' => 'boolean',
    ];
}
