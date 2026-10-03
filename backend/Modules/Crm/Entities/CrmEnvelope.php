<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmEnvelope extends Model
{
    protected $table = 'crm_envelopes';

    protected $fillable = [
        'company_id', 'deal_id', 'title', 'body', 'document_hash', 'status',
    ];

    public function signers(): HasMany
    {
        return $this->hasMany(CrmSigner::class, 'envelope_id');
    }
}
