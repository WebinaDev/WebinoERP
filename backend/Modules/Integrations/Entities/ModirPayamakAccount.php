<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ModirPayamakAccount extends Model
{
    protected $table = 'modirpayamak_accounts';

    protected $fillable = ['domain', 'balance', 'default_from', 'status'];

    protected $casts = ['balance' => 'decimal:2'];
}
