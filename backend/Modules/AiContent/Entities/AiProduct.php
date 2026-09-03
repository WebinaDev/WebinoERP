<?php

namespace Modules\AiContent\Entities;

use Illuminate\Database\Eloquent\Model;

class AiProduct extends Model
{
    protected $table = 'ai_content_products';

    protected $fillable = ['name', 'sku', 'missing', 'description', 'short_description', 'status', 'meta'];

    protected $casts = [
        'missing' => 'array',
        'meta' => 'array',
    ];
}
