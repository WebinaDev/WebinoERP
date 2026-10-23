<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eloquent model over the Customer 360 hub table `crm_customer_services`. */
class CustomerService extends Model
{
    protected $table = 'crm_customer_services';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}
