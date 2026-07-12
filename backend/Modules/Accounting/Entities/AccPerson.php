<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccPerson extends Model
{
    protected $table = 'acc_persons';

    protected $fillable = [
        'name', 'type', 'national_id', 'economic_code', 'mobile', 'address', 'category',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(AccountingInvoice::class, 'person_id');
    }
}
