<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ModirPayamakDomainNumber extends Model
{
    protected $table = 'modirpayamak_domain_numbers';

    protected $fillable = ['domain', 'number', 'role', 'label', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];
}
