<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $table = 'core_feature_flags';

    protected $fillable = [
        'key', 'enabled', 'rollout_percent', 'sandbox_only', 'description',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sandbox_only' => 'boolean',
        ];
    }
}
