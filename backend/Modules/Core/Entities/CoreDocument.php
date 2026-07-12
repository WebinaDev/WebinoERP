<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreDocument extends Model
{
    use SoftDeletes;

    protected $table = 'core_documents';

    protected $fillable = [
        'folder_id',
        'owner_id',
        'disk',
        'path',
        'name',
        'mime_type',
        'size_bytes',
        'last_uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'last_uploaded_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CoreDocumentVersion::class, 'document_id');
    }
}
