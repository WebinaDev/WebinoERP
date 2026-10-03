<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmOnboardingTemplateItem extends Model
{
    protected $table = 'hrm_onboarding_template_items';

    protected $fillable = ['template_id', 'title', 'description', 'document_category', 'sort_order', 'required'];

    protected $casts = ['required' => 'boolean'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(HrmOnboardingTemplate::class, 'template_id');
    }
}
