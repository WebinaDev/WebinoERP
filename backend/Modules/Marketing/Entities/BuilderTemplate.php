<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;

class BuilderTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'kind',
        'slug',
        'title',
        'is_default',
        'priority',
        'conditions',
        'draft',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'published' => 'array',
            'conditions' => 'array',
            'is_default' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public static function preferred(int $tenantId, string $kind): ?self
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
