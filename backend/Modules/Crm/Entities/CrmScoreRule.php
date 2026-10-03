<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmScoreRule extends Model
{
    protected $table = 'crm_score_rules';

    protected $fillable = [
        'name', 'kind', 'target', 'operator', 'match_value', 'weight', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
