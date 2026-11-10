<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesGiftCardTransaction extends Model
{
    protected $table = 'sales_gift_card_transactions';

    protected $fillable = ['gift_card_id', 'order_id', 'user_id', 'type', 'amount_minor', 'balance_after_minor', 'note'];

    protected $casts = ['amount_minor' => 'integer', 'balance_after_minor' => 'integer'];
}
