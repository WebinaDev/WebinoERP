<?php

namespace Modules\Docs\Entities;

use Illuminate\Database\Eloquent\Model;

class DocsContractParty extends Model
{
    protected $table = 'docs_contract_parties';

    protected $fillable = [
        'contract_id', 'role', 'name', 'national_id', 'economic_code',
        'registration_number', 'signatory_name', 'phone', 'email', 'address', 'postal_code',
    ];
}
