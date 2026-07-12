<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class CoreSystemLog extends Model
{
    protected $table = 'core_system_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }
}
