<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;

class BuilderCollectionItem extends Model
{
    protected $fillable = ['tenant_id', 'collection_id', 'slug', 'title', 'data', 'published', 'sort'];

    protected function casts(): array
    {
        return ['data' => 'array', 'published' => 'boolean', 'sort' => 'integer'];
    }
}
