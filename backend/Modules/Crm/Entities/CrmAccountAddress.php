<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmAccountAddress extends Model
{
    protected $table = 'crm_account_addresses';

    protected $fillable = ['account_id', 'type', 'title', 'province', 'city', 'line', 'postal_code', 'lat', 'lng', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'lat' => 'decimal:7', 'lng' => 'decimal:7'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}
