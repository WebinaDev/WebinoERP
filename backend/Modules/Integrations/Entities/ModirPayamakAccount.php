<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModirPayamakAccount extends Model
{
    protected $table = 'modirpayamak_accounts';

    protected $fillable = ['domain', 'balance', 'default_from', 'status'];

    protected $casts = ['balance' => 'decimal:2'];

    public function numbers(): HasMany
    {
        return $this->hasMany(ModirPayamakDomainNumber::class, 'domain', 'domain');
    }
}
