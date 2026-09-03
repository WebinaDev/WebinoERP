<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketingTeamMember extends Model
{
    protected $table = 'marketing_team_members';

    protected $fillable = ['name', 'role', 'bio', 'photo_url', 'social_links', 'sort_order', 'published'];

    protected $casts = ['social_links' => 'array', 'published' => 'boolean'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
