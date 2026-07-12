<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BaleCampaign extends Model
{
    protected $fillable = [
        'name', 'segment_key', 'variant', 'message_template', 'cta_text',
        'status', 'scheduled_for', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(BaleCampaignDelivery::class, 'campaign_id');
    }
}
