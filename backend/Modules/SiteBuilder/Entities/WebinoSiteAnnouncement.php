<?php

namespace Modules\SiteBuilder\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebinoSiteAnnouncement extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const LEVELS = ['info', 'warning', 'critical'];

    public const INBOX_AUDIENCES = ['all', 'staff', 'admins'];

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_SITES = 'sites';

    public const AUDIENCE_CATEGORY = 'category';

    public const AUDIENCE_TYPE = 'type';

    public const AUDIENCE_TAG = 'tag';

    protected $table = 'webino_site_announcements';

    protected $fillable = [
        'title_fa',
        'title_en',
        'body_fa',
        'body_en',
        'level',
        'inbox_audience',
        'status',
        'audience',
        'published_at',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebinoSiteAnnouncementDelivery::class, 'announcement_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function externalKey(): string
    {
        return 'erp-site-announcement:'.$this->id;
    }
}
