<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;

class MarketingSiteSetting extends Model
{
    protected $table = 'marketing_site_settings';

    protected $fillable = [
        'logo_url', 'favicon_url', 'site_name', 'active_theme_slug',
        'branding', 'nav', 'home_blocks', 'social_links',
    ];

    protected $casts = [
        'branding' => 'array',
        'nav' => 'array',
        'home_blocks' => 'array',
        'social_links' => 'array',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'site_name' => 'وبینا',
            'active_theme_slug' => 'webina-corporate-v1',
            'logo_url' => '/brand/logo.png',
            'favicon_url' => '/brand/favicon.png',
            'branding' => [
                'primary' => '#0054ff',
                'ink' => '#22242a',
                'paper' => '#ffffff',
                'foreground' => '#22242a',
                'description' => 'شرکت توسعه کسب و کار وبینا. خدمات دیجیتال و محصول‌های Dashboard، ERP، مستندات و سکوی میزبانی.',
            ],
            'home_blocks' => [
                ['type' => 'hero', 'enabled' => true],
                ['type' => 'stats', 'enabled' => true],
                ['type' => 'services', 'enabled' => true],
                ['type' => 'products', 'enabled' => true],
                ['type' => 'solutions', 'enabled' => true],
                ['type' => 'process', 'enabled' => true],
                ['type' => 'portfolio_teaser', 'enabled' => true],
                ['type' => 'testimonials', 'enabled' => true],
                ['type' => 'announcements', 'enabled' => true],
                ['type' => 'blog', 'enabled' => true],
                ['type' => 'consultation_cta', 'enabled' => true],
            ],
        ]);
    }
}
