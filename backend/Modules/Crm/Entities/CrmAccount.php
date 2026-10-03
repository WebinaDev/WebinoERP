<?php

namespace Modules\Crm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

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

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'account_id');
    }

    public function portalUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crm_account_users', 'account_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'customer_account_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(PrjTicket::class, 'customer_account_id');
    }

    public function siteProvisions(): HasMany
    {
        return $this->hasMany(WebinoSiteProvision::class, 'crm_account_id');
    }
}
