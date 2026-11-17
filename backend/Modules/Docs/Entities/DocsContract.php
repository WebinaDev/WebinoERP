<?php

namespace Modules\Docs\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocsContract extends Model
{
    protected $table = 'docs_contracts';

    protected $fillable = [
        'contract_number', 'title', 'party_name', 'status', 'body', 'meta',
        'crm_deal_id', 'crm_customer_id', 'project_id', 'total_amount',
        'currency', 'start_date', 'end_date', 'version', 'is_locked',
        'cancelled_reason', 'signed_at', 'created_by',
    ];

    protected $casts = [
        'signed_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'meta' => 'array',
        'total_amount' => 'decimal:2',
        'is_locked' => 'boolean',
        'version' => 'integer',
    ];

    public function parties(): HasMany
    {
        return $this->hasMany(DocsContractParty::class, 'contract_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(DocsContractInstallment::class, 'contract_id');
    }

    public function envelopes(): HasMany
    {
        return $this->hasMany(DocsSignatureEnvelope::class, 'contract_id');
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(DocsContractAddendum::class, 'contract_id');
    }
}
