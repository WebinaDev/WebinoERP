<?php

namespace Modules\SiteBuilder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebinoBusinessCategory extends Model
{
    protected $table = 'webino_business_categories';

    protected $fillable = [
        'slug', 'name_fa', 'name_en', 'icon', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function types(): HasMany
    {
        return $this->hasMany(WebinoBusinessType::class, 'category_id');
    }
}
