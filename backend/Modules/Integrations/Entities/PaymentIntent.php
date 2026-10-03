<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentIntent extends Model
{
    protected $table = 'payment_intents';

    protected $fillable = [
        'public_id', 'idempotency_key', 'user_id', 'domain', 'payable_type', 'payable_id',
        'mode', 'gateway', 'currency', 'base_amount', 'fee_percent', 'fee_amount', 'total_amount',
        'fee_applied', 'status', 'authority', 'provider_ref', 'provider_tx', 'redirect_url',
        'return_url', 'callback_token', 'description', 'mobile', 'meta', 'provider_payload',
        'verified_at', 'settled_at', 'failed_at', 'side_effects_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'integer',
            'fee_percent' => 'float',
            'fee_amount' => 'integer',
            'total_amount' => 'integer',
            'fee_applied' => 'boolean',
            'meta' => 'array',
            'provider_payload' => 'array',
            'verified_at' => 'datetime',
            'settled_at' => 'datetime',
            'failed_at' => 'datetime',
            'side_effects_at' => 'datetime',
        ];
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(PaymentLedgerEntry::class, 'payment_intent_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'redirected', 'verified'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'payment_id' => $this->public_id,
            'id' => $this->id,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'domain' => $this->domain,
            'mode' => $this->mode,
            'gateway' => $this->gateway,
            'currency' => $this->currency,
            'base_amount' => (int) $this->base_amount,
            'fee_percent' => (float) $this->fee_percent,
            'fee_amount' => (int) $this->fee_amount,
            'total_amount' => (int) $this->total_amount,
            'fee_applied' => (bool) $this->fee_applied,
            'status' => $this->status,
            'authority' => $this->authority,
            'provider_ref' => $this->provider_ref,
            'redirect_url' => $this->redirect_url,
            'return_url' => $this->return_url,
            'description' => $this->description,
            'failure_reason' => $this->failure_reason,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
