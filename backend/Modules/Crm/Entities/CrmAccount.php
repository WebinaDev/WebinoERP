<?php

namespace Modules\Crm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\CoreLicense;
use Modules\Docs\Entities\DocsContract;
use Modules\Docs\Entities\DocsFile;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;
use Modules\Marketplace\Entities\MarketplaceOrder;
use Modules\Platform\Entities\PlatformProject;
use Modules\Platform\Entities\PlatformResource;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\PrjAppointment;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class CrmAccount extends Model
{
    use SoftDeletes;

    protected $table = 'crm_accounts';

    protected $fillable = [
        'name', 'account_code', 'website', 'parent_id', 'type', 'tax_id', 'industry',
        'employees_count', 'annual_revenue', 'billing_address', 'shipping_address',
        'owner_id', 'description', 'created_by',
        // Customer 360 Hub
        'customer_kind', 'hub_status', 'brand_name', 'national_id', 'economic_code', 'registration_no',
        'postal_code', 'address_line', 'city', 'province', 'country', 'logo_url', 'kyc_status', 'kyc_note',
        'kyc_verified_at', 'preferred_language', 'preferred_channel', 'timezone', 'marketing_sms_opt_out',
        'referrer_account_id', 'partner_account_id', 'credit_limit', 'allowed_ips', 'suspended_at',
        // Customer 360 phase 0
        'phone', 'mobile', 'email', 'socials', 'tags', 'source', 'segment', 'custom_fields',
        'payment_terms_days', 'price_list_id', 'currency', 'default_discount_pct', 'iban',
        'account_manager_id', 'merged_into_id', 'last_activity_at', 'lifetime_value',
    ];

    protected $hidden = ['iban'];

    protected function casts(): array
    {
        return [
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'annual_revenue' => 'decimal:2',
            'allowed_ips' => 'array',
            'marketing_sms_opt_out' => 'boolean',
            'kyc_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'socials' => 'array',
            'tags' => 'array',
            'custom_fields' => 'array',
            'iban' => 'encrypted',
            'default_discount_pct' => 'decimal:2',
            'lifetime_value' => 'decimal:2',
            'last_activity_at' => 'datetime',
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

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referrer_account_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'partner_account_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CrmAccountAddress::class, 'account_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'account_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'converted_to_account_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'customer_account_id');
    }

    public function docContracts(): HasMany
    {
        return $this->hasMany(DocsContract::class, 'crm_account_id');
    }

    public function docFiles(): HasMany
    {
        return $this->hasMany(DocsFile::class, 'crm_account_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class, 'account_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(CustomerDomain::class, 'account_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(CustomerService::class, 'account_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'customer_id');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(CoreLicense::class, 'crm_account_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(PrjAppointment::class, 'customer_account_id');
    }

    public function marketplaceOrders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class, 'crm_account_id');
    }

    public function basalamConnections(): HasMany
    {
        return $this->hasMany(MarketplaceBasalamConnection::class, 'crm_account_id');
    }

    public function platformProjects(): HasMany
    {
        return $this->hasMany(PlatformProject::class, 'crm_account_id');
    }

    public function platformResources(): HasMany
    {
        return $this->hasMany(PlatformResource::class, 'crm_account_id');
    }
}
