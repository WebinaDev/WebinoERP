<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaleCampaignDelivery extends Model
{
    protected $fillable = [
        'campaign_id', 'chat_id', 'variant', 'status', 'delivered_at', 'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BaleCampaign::class, 'campaign_id');
    }
}
