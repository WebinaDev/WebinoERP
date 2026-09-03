<?php

namespace Modules\AiContent\Entities;

use Illuminate\Database\Eloquent\Model;

class AiAttrTemplate extends Model
{
    protected $table = 'ai_content_attr_templates';

    protected $fillable = ['product_cat_id', 'category_name', 'attribute_ids', 'labels', 'draft'];

    protected $casts = [
        'product_cat_id' => 'integer',
        'attribute_ids' => 'array',
        'labels' => 'array',
        'draft' => 'array',
    ];
}
