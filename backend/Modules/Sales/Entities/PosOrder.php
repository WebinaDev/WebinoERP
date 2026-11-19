<?php

namespace Modules\Sales\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrder extends Model
{
    protected $table = 'pos_orders';

    protected $fillable = [
        'number', 'type', 'session_id', 'register_id', 'user_id', 'company_id', 'branch_id', 'customer_id',
        'customer_name', 'refund_of_id', 'status', 'subtotal', 'discount_total', 'tax_total', 'total',
        'paid_total', 'change_due', 'sales_invoice_id', 'journal_entry_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_due' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PosOrderLine::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'order_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'register_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function refundOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refund_of_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'refund_of_id');
    }
}
