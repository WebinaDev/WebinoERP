<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;

class MarketingNavMenu extends Model
{
    public const LOCATIONS = ['header_main', 'top_bar', 'mobile', 'mega_menu', 'footer_1', 'footer_2', 'footer_3', 'footer_4', 'footer_bottom'];

    protected $table = 'marketing_nav_menus';

    protected $fillable = ['name', 'slug', 'items', 'locations'];

    protected $casts = ['items' => 'array', 'locations' => 'array'];
}
