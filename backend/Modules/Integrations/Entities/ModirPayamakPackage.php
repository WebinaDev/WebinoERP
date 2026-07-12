<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ModirPayamakPackage extends Model
{
    protected $table = 'modirpayamak_packages';

    protected $fillable = ['name', 'amount', 'sms_units', 'is_active', 'sort_order'];

    protected $casts = ['amount' => 'decimal:2', 'is_active' => 'boolean'];
}
