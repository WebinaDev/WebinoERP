<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use Modules\Crm\Entities\CrmLead;

class Contract extends Model
{
    protected $table = 'prj_contracts';

    protected $fillable = [
        'title',
        'project_id',
        'status',
        'amount',
        'installments_data',
        'notes',
        'signed_at',
        'lead_id',
        'customer_account_id',
        'customer_user_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installments_data' => 'array',
            'signed_at' => 'datetime',
        ];
    }

    public function installments(): HasMany
    {
        return $this->hasMany(ContractInstallment::class, 'contract_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
