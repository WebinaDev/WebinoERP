<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreAutomationRule extends Model
{
    protected $table = 'core_automation_rules';

    protected $fillable = [
        'name',
        'trigger',
        'conditions',
        'actions',
        'is_active',
        'priority',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CoreAutomationRun::class, 'rule_id');
    }
}
