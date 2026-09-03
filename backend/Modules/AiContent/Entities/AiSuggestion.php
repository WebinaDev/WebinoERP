<?php

namespace Modules\AiContent\Entities;

use Illuminate\Database\Eloquent\Model;

class AiSuggestion extends Model
{
    protected $table = 'ai_content_suggestions';

    protected $fillable = ['kind', 'suggestions'];

    protected $casts = [
        'suggestions' => 'array',
    ];
}
