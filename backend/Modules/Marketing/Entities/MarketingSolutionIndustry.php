<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingSolutionIndustry extends Model
{
    protected $table = 'marketing_solution_industries';

    protected $casts = ['seo' => 'array'];

    protected $fillable = ['seo', 'slug', 'name', 'description', 'sort_order'];

    public function pages(): HasMany
    {
        return $this->hasMany(MarketingSolutionPage::class, 'industry_id');
    }
}
