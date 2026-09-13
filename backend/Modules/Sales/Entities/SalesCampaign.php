<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Crm\Entities\CrmDeal;

class SalesCampaign extends Model
{
    protected $table = 'sales_campaigns';

    protected $fillable = [
        'name',
        'description',
        'status',
        'channel',
        'budget',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $appends = ['lead_count'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'budget' => 'decimal:2',
    ];

    public function getLeadCountAttribute(): int
    {
        if (! class_exists(CrmDeal::class)) {
            return 0;
        }

        try {
            return (int) CrmDeal::query()
                ->where(function ($q) {
                    $q->where('campaign_source', (string) $this->id)
                        ->orWhere('campaign_source', $this->name);
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
