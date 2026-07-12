<?php

namespace Modules\Scm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScmWarehouseDocument extends Model
{
    protected $table = 'scm_warehouse_documents';

    protected $fillable = [
        'type', 'warehouse_id', 'number', 'document_date', 'status', 'reference', 'items', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'items' => 'array',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ScmWarehouse::class, 'warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
