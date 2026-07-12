<?php

namespace Modules\Projects\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProInvoice extends Model
{
    protected $table = 'prj_pro_invoices';

    protected $fillable = [
        'number', 'contract_id', 'status', 'total', 'items', 'discount', 'notes',
        'customer_user_id', 'project_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'discount' => 'decimal:2',
            'items' => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }
}
