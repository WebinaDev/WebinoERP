<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;

class SiteTheme extends Model
{
    protected $table = 'site_themes';

    protected $fillable = [
        'slug', 'name_fa', 'name_en', 'preview_url', 'package_path',
        'business_types', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'business_types' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
