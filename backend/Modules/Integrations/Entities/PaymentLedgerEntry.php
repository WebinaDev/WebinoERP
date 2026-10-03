<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLedgerEntry extends Model
{
    protected $table = 'payment_ledger_entries';

    protected $fillable = [
        'payment_intent_id', 'entry_type', 'amount', 'status', 'note', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'meta' => 'array',
        ];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}
