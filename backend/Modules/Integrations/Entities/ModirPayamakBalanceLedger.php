<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ModirPayamakBalanceLedger extends Model
{
    protected $table = 'modirpayamak_balance_ledger';

    protected $fillable = ['domain', 'type', 'amount', 'balance_after', 'reference', 'meta'];

    protected $casts = ['amount' => 'decimal:2', 'balance_after' => 'decimal:2', 'meta' => 'array'];
}
