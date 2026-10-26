<?php

namespace Modules\SiteBuilder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WebinoDashboardFeature extends Model
{
    protected $table = 'webino_dashboard_features';

    protected $fillable = [
        'slug', 'name_fa', 'name_en', 'module_slug', 'icon', 'is_builtin', 'is_addon', 'default_enabled', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_addon' => 'boolean',
            'is_builtin' => 'boolean',
            'default_enabled' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function businessTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            WebinoBusinessType::class,
            'webino_business_type_features',
            'feature_id',
            'business_type_id'
        )->withPivot(['is_required', 'default_selected']);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(
            WebinoPackage::class,
            'webino_package_features',
            'feature_id',
            'package_id'
        );
    }
}
