<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmCompany extends Model
{
    protected $table = 'crm_companies';

    protected $fillable = [
        'name', 'legal_name', 'english_name', 'brand_name', 'national_id', 'registration_number', 'economic_code',
        'currency_code', 'address', 'phone', 'email', 'website', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
