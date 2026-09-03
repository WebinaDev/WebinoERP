<?php

namespace Modules\AiContent\Entities;

use Illuminate\Database\Eloquent\Model;

class AiPage extends Model
{
    protected $table = 'ai_content_pages';

    protected $fillable = ['title', 'status', 'url', 'page_prompt', 'has_elementor', 'elementor_url', 'content'];

    protected $casts = [
        'has_elementor' => 'boolean',
    ];
}
