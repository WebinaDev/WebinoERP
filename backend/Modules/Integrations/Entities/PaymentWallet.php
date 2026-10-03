<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class PaymentWallet extends Model
{
    protected $table = 'payment_wallets';

    protected $fillable = ['domain', 'balance'];

    protected function casts(): array
    {
        return ['balance' => 'float'];
    }
}
