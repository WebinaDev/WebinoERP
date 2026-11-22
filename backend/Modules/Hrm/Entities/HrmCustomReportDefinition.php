<?php

namespace Modules\Hrm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmCustomReportDefinition extends Model
{
    protected $table = 'hrm_custom_report_definitions';

    protected $fillable = [
        'code',
        'title_fa',
        'title_en',
        'category',
        'selected_columns',
        'filter_criteria',
        'sorting_rules',
        'is_system_preset',
        'created_by',
    ];

    protected $casts = [
        'selected_columns' => 'array',
        'filter_criteria' => 'array',
        'sorting_rules' => 'array',
        'is_system_preset' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
