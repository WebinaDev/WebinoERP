<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class HrmPayrollSetting extends Model
{
    protected $table = 'hrm_payroll_settings';

    protected $fillable = ['key', 'value'];

    /** Preserve scalars (rates) and arrays (tax brackets). */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }
                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            },
            set: fn ($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
        );
    }
}
