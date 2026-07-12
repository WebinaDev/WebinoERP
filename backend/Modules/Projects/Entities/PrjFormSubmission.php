<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Entities\CrmLead;

class PrjFormSubmission extends Model
{
    protected $table = 'prj_form_submissions';

    protected $fillable = [
        'form_id',
        'data',
        'ip',
        'user_agent',
        'converted_lead_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(PrjForm::class, 'form_id');
    }

    public function convertedLead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'converted_lead_id');
    }
}
