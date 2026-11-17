<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class MarketingGrowthAttribution extends Model
{
    protected $table = 'marketing_growth_attributions';

    protected $fillable = [
        'campaign_source', 'utm_campaign', 'utm_medium', 'landing_page_url',
        'crm_lead_id', 'crm_deal_id', 'sales_quote_id', 'sales_invoice_id',
        'realized_revenue', 'converted_at',
    ];

    protected $casts = [
        'realized_revenue' => 'decimal:2',
        'converted_at' => 'datetime',
    ];
}
