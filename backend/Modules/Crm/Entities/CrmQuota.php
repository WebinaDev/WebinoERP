<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmQuota extends Model
{
    protected $table = 'crm_quotas';

    protected $fillable = ['user_id', 'pipeline_id', 'period_start', 'period_end', 'amount'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'decimal:2',
        ];
    }
}
