<?php

namespace Modules\Crm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmAccount extends Model
{
    use SoftDeletes;

    protected $table = 'crm_accounts';

    protected $fillable = [
        'name', 'account_code', 'website', 'parent_id', 'type', 'tax_id', 'industry',
        'employees_count', 'annual_revenue', 'billing_address', 'shipping_address',
        'owner_id', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'annual_revenue' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CrmContact::class, 'account_id');
    }
}
