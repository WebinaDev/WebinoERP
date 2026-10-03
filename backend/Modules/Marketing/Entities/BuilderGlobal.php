<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;

class BuilderGlobal extends Model
{
    protected $table = 'builder_globals';

    protected $fillable = [
        'tenant_id',
        'draft',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'published' => 'array',
        ];
    }
}
