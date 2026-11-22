<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmOrgStructureHistory extends Model
{
    protected $table = 'hrm_org_structure_histories';

    protected $fillable = [
        'position_id',
        'employee_id',
        'effective_from',
        'effective_to',
        'change_type',
        'decree_no',
        'change_reason',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(HrmOrgPosition::class, 'position_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    /**
     * Scope for filtering structural changes by employee
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope for reporting changes within a date range
     */
    public function scopeInPeriod(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('effective_from', [$startDate, $endDate]);
    }

    /**
     * Scope for specific change types (e.g. appointment, promotion, transfer, bulk_salary_adjustment)
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('change_type', $type);
    }

    /**
     * Human-readable label for change type in Persian
     */
    public function getChangeTypeLabelAttribute(): string
    {
        return match ($this->change_type) {
            'appointment' => 'انتصاب',
            'promotion' => 'ارتقاء رتبه/پست',
            'transfer' => 'انتقال سازمانی',
            'demotion' => 'تنزل رتبه',
            'vacancy' => 'بلاتصدی/خروج',
            'bulk_salary_adjustment' => 'اصلاح گروهی احکام کارگزینی',
            default => $this->change_type,
        };
    }
}
