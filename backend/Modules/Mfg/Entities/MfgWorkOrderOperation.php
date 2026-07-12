<?php

namespace Modules\Mfg\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfgWorkOrderOperation extends Model
{
    protected $table = 'mfg_work_order_operations';

    protected $fillable = ['work_order_id', 'sequence', 'name', 'status', 'duration_minutes'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MfgWorkOrder::class, 'work_order_id');
    }
}
