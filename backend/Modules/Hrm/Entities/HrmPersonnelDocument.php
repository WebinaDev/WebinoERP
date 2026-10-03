<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmPersonnelDocument extends Model
{
    protected $table = 'hrm_personnel_documents';

    protected $fillable = [
        'employee_id', 'title', 'category', 'file_path', 'original_name', 'mime', 'size',
        'uploaded_by', 'expires_at', 'document_status', 'signer_name', 'signer_user_id',
        'signed_at', 'signature_path', 'signature_placeholder', 'audit_log',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'signed_at' => 'datetime',
        'audit_log' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
