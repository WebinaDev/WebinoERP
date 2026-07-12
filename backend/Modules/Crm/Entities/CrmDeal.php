<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmDeal extends Model
{
    use SoftDeletes;

    protected $table = 'crm_deals';

    protected $fillable = [
        'name', 'account_id', 'contact_id', 'pipeline_id', 'stage_id', 'amount',
        'probability', 'close_date', 'type', 'loss_reason', 'campaign_source',
        'assigned_to', 'description', 'won_at', 'lost_at', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'close_date' => 'date',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }
}
