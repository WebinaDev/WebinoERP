<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmOnboardingTemplate extends Model
{
    protected $table = 'hrm_onboarding_templates';

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(HrmOnboardingTemplateItem::class, 'template_id')->orderBy('sort_order');
    }
}
