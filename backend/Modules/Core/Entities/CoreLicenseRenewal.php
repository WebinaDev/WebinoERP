<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreLicenseRenewal extends Model
{
    protected $table = 'core_license_renewals';

    protected $fillable = [
        'license_id', 'price_id', 'crm_account_id', 'months', 'price_irr', 'discount_percent', 'amount_irr',
        'status', 'return_url', 'payment_id', 'sales_invoice_id', 'previous_expires_at', 'new_expires_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'price_irr' => 'integer',
            'amount_irr' => 'integer',
            'discount_percent' => 'float',
            'previous_expires_at' => 'datetime',
            'new_expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(CoreLicense::class, 'license_id');
    }
}
