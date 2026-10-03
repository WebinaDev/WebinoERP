<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class DashboardWidgetPref extends Model
{
    protected $table = 'dashboard_widget_prefs';

    protected $fillable = ['user_id', 'widget_key', 'is_visible', 'sort_order'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }
}
