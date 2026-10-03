<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class PaymentWalletLedger extends Model
{
    protected $table = 'payment_wallet_ledgers';

    protected $fillable = ['domain', 'amount', 'balance_after', 'payment_intent_id', 'note'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'balance_after' => 'float',
        ];
    }
}
