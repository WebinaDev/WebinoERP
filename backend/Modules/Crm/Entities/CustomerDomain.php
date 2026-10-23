<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eloquent model over the Customer 360 hub table `crm_customer_domains`. */
class CustomerDomain extends Model
{
    protected $table = 'crm_customer_domains';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['nameservers' => 'array', 'auto_renew' => 'boolean', 'registered_at' => 'date', 'expires_at' => 'date'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}
