<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmCampaignSpend extends Model
{
    protected $table = 'crm_campaign_spends';

    protected $fillable = [
        'campaign_key', 'name', 'spent', 'currency_code', 'starts_on', 'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'spent' => 'float',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
