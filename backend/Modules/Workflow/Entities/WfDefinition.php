<?php

namespace Modules\Workflow\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WfDefinition extends Model
{
    protected $table = 'wf_definitions';

    protected $fillable = [
        'name',
        'module_slug',
        'entity_type',
        'conditions',
        'steps',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'steps' => 'array',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WfInstance::class, 'definition_id');
    }
}
