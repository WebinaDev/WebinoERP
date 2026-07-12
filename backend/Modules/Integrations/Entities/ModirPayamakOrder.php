<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModirPayamakOrder extends Model
{
    protected $table = 'modirpayamak_orders';

    protected $fillable = ['domain', 'package_id', 'amount', 'authority', 'status', 'ref_id', 'user_id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ModirPayamakPackage::class, 'package_id');
    }
}
