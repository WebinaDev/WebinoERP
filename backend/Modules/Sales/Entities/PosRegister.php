<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosRegister extends Model
{
    protected $table = 'pos_registers';

    protected $fillable = [
        'company_id', 'branch_id', 'name', 'code', 'warehouse_id', 'tax_rate', 'tax_inclusive',
        'allow_negative_stock', 'payment_methods', 'receipt_header', 'receipt_footer', 'is_active',
    ];

    public const METHODS = ['cash', 'card', 'transfer', 'on_account'];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'payment_methods' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return list<string> */
    public function enabledMethods(): array
    {
        $m = array_values(array_intersect(self::METHODS, (array) ($this->payment_methods ?: self::METHODS)));

        return $m ?: self::METHODS;
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PosSession::class, 'register_id');
    }
}
