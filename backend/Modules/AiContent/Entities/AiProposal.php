<?php

namespace Modules\AiContent\Entities;

use Illuminate\Database\Eloquent\Model;

class AiProposal extends Model
{
    protected $table = 'ai_content_proposals';

    protected $fillable = ['kind', 'product_id', 'product_name', 'current_json', 'proposed_json', 'status'];

    protected $casts = [
        'product_id' => 'integer',
        'current_json' => 'array',
        'proposed_json' => 'array',
    ];
}
