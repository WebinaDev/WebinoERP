<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmSigner extends Model
{
    protected $table = 'crm_signers';

    protected $fillable = [
        'envelope_id', 'name', 'national_id', 'mobile', 'role', 'otp_hash', 'signed_at', 'signature_hash',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }
}
