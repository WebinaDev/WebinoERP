<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccCashAccount extends Model
{
    protected $table = 'acc_cash_accounts';

    protected $fillable = [
        'name', 'type', 'bank_name', 'account_number', 'sheba', 'card_number', 'is_active', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function receiptVouchers(): HasMany
    {
        return $this->hasMany(AccReceiptVoucher::class, 'cash_account_id');
    }
}
