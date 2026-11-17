<?php

namespace Modules\Org\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Entities\CrmCompany;

class OrgDocumentSequence extends Model
{
    protected $table = 'org_document_sequences';

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_type',
        'prefix',
        'next_number',
        'padding_zeros',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'padding_zeros' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(OrgBranch::class, 'branch_id');
    }
}
