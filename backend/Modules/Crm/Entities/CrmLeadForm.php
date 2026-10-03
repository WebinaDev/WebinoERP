<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmLeadForm extends Model
{
    protected $table = 'crm_lead_forms';

    protected $fillable = [
        'company_id', 'name', 'slug', 'fields', 'landing_url', 'active', 'notify_user_id',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'active' => 'boolean',
        ];
    }
}
