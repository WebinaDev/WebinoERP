<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformMailAlias extends Model
{
    protected $table = 'platform_mail_aliases';

    protected $fillable = [
        'mail_domain_id', 'source_email', 'destination_email', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function mailDomain(): BelongsTo
    {
        return $this->belongsTo(PlatformMailDomain::class, 'mail_domain_id');
    }
}
