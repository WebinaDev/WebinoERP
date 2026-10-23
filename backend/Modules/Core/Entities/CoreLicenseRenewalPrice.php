<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class CoreLicenseRenewalPrice extends Model
{
    protected $table = 'core_license_renewal_prices';

    protected $fillable = ['product', 'plan', 'plan_name', 'months', 'price_irr', 'discount_percent', 'label', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'price_irr' => 'integer',
            'discount_percent' => 'float',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function finalIrr(): int
    {
        $discount = max(0.0, min(100.0, (float) ($this->discount_percent ?? 0)));

        return (int) (round($this->price_irr * (100 - $discount) / 100 / 10) * 10);
    }
}
