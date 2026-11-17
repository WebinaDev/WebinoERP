<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformMailDomain extends Model
{
    protected $table = 'platform_mail_domains';

    protected $fillable = [
        'domain_id', 'domain_name', 'mx_record', 'spf_record',
        'dkim_selector', 'dkim_public_key', 'dkim_private_key', 'dmarc_record',
        'dns_status', 'is_active', 'max_mailboxes', 'quota_bytes_total', 'last_dns_checked_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_dns_checked_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(PlatformDomain::class, 'domain_id');
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(PlatformMailbox::class, 'mail_domain_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(PlatformMailAlias::class, 'mail_domain_id');
    }
}
