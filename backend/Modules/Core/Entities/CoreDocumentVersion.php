<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreDocumentVersion extends Model
{
    protected $table = 'core_document_versions';

    protected $fillable = [
        'document_id',
        'version_no',
        'disk',
        'path',
        'size_bytes',
        'uploaded_by',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(CoreDocument::class, 'document_id');
    }
}
