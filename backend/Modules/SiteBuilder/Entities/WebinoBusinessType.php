<?php

namespace Modules\SiteBuilder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebinoBusinessType extends Model
{
    protected $table = 'webino_business_types';

    protected $fillable = [
        'category_id', 'slug', 'name_fa', 'name_en', 'description_fa', 'description_en',
        'theme_preset', 'default_module_slugs', 'nav_preset', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_module_slugs' => 'array',
            'nav_preset' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WebinoBusinessCategory::class, 'category_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(
            WebinoDashboardFeature::class,
            'webino_business_type_features',
            'business_type_id',
            'feature_id'
        )->withPivot(['is_required', 'default_selected']);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(WebinoPackage::class, 'business_type_id');
    }
}
