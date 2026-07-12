<?php

namespace Modules\SiteBuilder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WebinoPackage extends Model
{
    protected $table = 'webino_packages';

    protected $fillable = [
        'sku', 'name_fa', 'name_en', 'business_type_id', 'price', 'billing_period', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(WebinoBusinessType::class, 'business_type_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(
            WebinoDashboardFeature::class,
            'webino_package_features',
            'package_id',
            'feature_id'
        );
    }
}
