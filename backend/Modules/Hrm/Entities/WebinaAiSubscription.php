<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class WebinaAiSubscription extends Model
{
    protected $table = 'webina_ai_subscriptions';

    protected $fillable = [
        'tenant_domain',
        'tenant_name',
        'plan_sku',
        'plan_title',
        'api_token_hash',
        'gateway_endpoint',
        'price_toman',
        'monthly_quota_tokens',
        'used_tokens_current_cycle',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'price_toman' => 'decimal:2',
        'monthly_quota_tokens' => 'integer',
        'used_tokens_current_cycle' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
