<?php

namespace Modules\AiContent\Entities;

use Illuminate\Database\Eloquent\Model;

class AiJob extends Model
{
    protected $table = 'ai_content_jobs';

    protected $fillable = ['job_type', 'target_type', 'target_id', 'payload', 'status', 'provider', 'model', 'tokens_in', 'tokens_out', 'cost_toman', 'error_message', 'result_summary', 'attempts', 'started_at', 'finished_at'];

    protected $casts = [
        'payload' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'cost_toman' => 'float',
        'attempts' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'target_id' => 'integer',
    ];
}
