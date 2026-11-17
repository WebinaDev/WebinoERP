<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformDnsRecord extends Model
{
    protected $table = 'platform_dns_records';

    protected $fillable = [
        'domain_id',
        'type',
        'name',
        'value',
        'ttl',
        'priority',
        'is_proxied',
        'status',
    ];

    protected $casts = [
        'is_proxied' => 'boolean',
        'ttl' => 'integer',
        'priority' => 'integer',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(PlatformDomain::class, 'domain_id');
    }
}
