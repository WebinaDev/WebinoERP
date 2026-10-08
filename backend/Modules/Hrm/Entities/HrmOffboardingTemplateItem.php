<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmOffboardingTemplateItem extends Model
{
    protected $table = 'hrm_offboarding_template_items';

    protected $fillable = [
        'template_id', 'title', 'description', 'kind', 'owner', 'sort_order', 'required',
    ];

    protected $casts = ['required' => 'boolean'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(HrmOffboardingTemplate::class, 'template_id');
    }
}
