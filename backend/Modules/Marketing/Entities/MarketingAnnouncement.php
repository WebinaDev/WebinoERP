<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketingAnnouncement extends Model
{
    protected $table = 'marketing_announcements';

    protected $fillable = ['title', 'body', 'pinned', 'published', 'starts_at', 'ends_at'];

    protected $casts = [
        'pinned' => 'boolean',
        'published' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('published', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
