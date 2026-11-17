<?php

namespace Modules\Docs\Entities;

use Illuminate\Database\Eloquent\Model;

class DocsSignatureEnvelope extends Model
{
    protected $table = 'docs_signature_envelopes';

    protected $fillable = [
        'contract_id', 'envelope_token', 'signer_name', 'signer_mobile',
        'otp_code_hash', 'otp_expires_at', 'status', 'signed_ip',
        'signed_user_agent', 'digital_certificate_hash', 'signed_at',
    ];

    protected $hidden = [
        'otp_code_hash',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'signed_at' => 'datetime',
    ];
}
