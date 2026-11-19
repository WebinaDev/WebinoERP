<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class PosCashMovement extends Model
{
    protected $table = 'pos_cash_movements';

    protected $fillable = ['session_id', 'type', 'amount', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
