<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;

class OfflineOp extends Model
{
    protected $table = 'prj_offline_ops';

    protected $fillable = [
        'user_id', 'client_id', 'action', 'payload', 'result',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
        ];
    }
}
