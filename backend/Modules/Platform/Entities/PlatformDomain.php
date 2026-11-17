<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class PlatformDomain extends Model
{
    protected $table = 'platform_domains';

    protected $fillable = [
        'resource_id',
        'crm_customer_id',
        'domain',
        'force_https',
        'hsts',
        'ssl_status',
        'ssl_expires_at',
        'ssl_issuer',
        'ssl_auto_renew',
        'is_wildcard',
        'last_uptime_status',
        'last_uptime_checked_at',
        'redirect_to',
    ];

    protected function casts(): array
    {
        return [
            'force_https' => 'boolean',
            'hsts' => 'boolean',
            'ssl_auto_renew' => 'boolean',
            'is_wildcard' => 'boolean',
            'ssl_expires_at' => 'datetime',
            'last_uptime_checked_at' => 'datetime',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(PlatformResource::class, 'resource_id');
    }

    public function dnsRecords(): HasMany
    {
        return $this->hasMany(PlatformDnsRecord::class, 'domain_id');
    }

    public function uptimeChecks(): HasMany
    {
        return $this->hasMany(PlatformUptimeCheck::class, 'domain_id');
    }

    public function mailDomain(): HasOne
    {
        return $this->hasOne(PlatformMailDomain::class, 'domain_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (in_array('uuid', $model->getFillable(), true) && empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
