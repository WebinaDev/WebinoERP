<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class PosPayment extends Model
{
    protected $table = 'pos_payments';

    protected $fillable = ['order_id', 'session_id', 'method', 'amount', 'reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
