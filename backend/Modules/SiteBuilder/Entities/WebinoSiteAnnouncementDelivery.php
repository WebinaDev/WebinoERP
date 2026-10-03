<?php

namespace Modules\SiteBuilder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebinoSiteAnnouncementDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVOKED = 'revoked';

    protected $table = 'webino_site_announcement_deliveries';

    protected $fillable = [
        'announcement_id',
        'site_provision_id',
        'status',
        'attempts',
        'last_error',
        'delivered_at',
        'read_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(WebinoSiteAnnouncement::class, 'announcement_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebinoSiteProvision::class, 'site_provision_id');
    }
}
