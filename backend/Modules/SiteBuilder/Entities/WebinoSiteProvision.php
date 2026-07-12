<?php

namespace Modules\SiteBuilder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\CoreLicense;
use Modules\Crm\Entities\CrmAccount;

class WebinoSiteProvision extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_SSL_PENDING = 'ssl_pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $table = 'webino_site_provisions';

    protected $fillable = [
        'crm_account_id', 'package_id', 'license_id', 'slug', 'domain', 'subdomain',
        'uses_custom_domain', 'status', 'wizard_payload', 'error_log', 'server_host_id',
        'provision_token', 'created_by', 'launched_at', 'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'wizard_payload' => 'array',
            'uses_custom_domain' => 'boolean',
            'launched_at' => 'datetime',
            'ready_at' => 'datetime',
        ];
    }

    public function crmAccount(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(WebinoPackage::class, 'package_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(CoreLicense::class, 'license_id');
    }
}
