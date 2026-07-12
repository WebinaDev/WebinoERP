<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingMediaFolder extends Model
{
    protected $table = 'marketing_media_folders';

    protected $fillable = ['parent_id', 'name'];

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}

class MarketingMedia extends Model
{
    protected $table = 'marketing_media';

    protected $fillable = ['folder_id', 'path', 'mime', 'alt', 'public_url', 'wp_id', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MarketingMediaFolder::class, 'folder_id');
    }
}

class MarketingDownload extends Model
{
    protected $table = 'marketing_downloads';

    protected $fillable = ['title', 'file_id', 'category', 'published', 'sort_order'];

    protected $casts = ['published' => 'boolean'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(MarketingMedia::class, 'file_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}

class MarketingServiceCategory extends Model
{
    protected $table = 'marketing_service_categories';

    protected $fillable = ['parent_id', 'slug', 'name', 'description', 'sort_order'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function services(): HasMany
    {
        return $this->hasMany(MarketingService::class, 'category_id')->orderBy('sort_order');
    }
}

class MarketingService extends Model
{
    protected $table = 'marketing_services';

    protected $fillable = ['category_id', 'slug', 'title', 'excerpt', 'body', 'published', 'sort_order'];

    protected $casts = ['published' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketingServiceCategory::class, 'category_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}

class MarketingSolutionIndustry extends Model
{
    protected $table = 'marketing_solution_industries';

    protected $fillable = ['slug', 'name', 'description', 'sort_order'];

    public function pages(): HasMany
    {
        return $this->hasMany(MarketingSolutionPage::class, 'industry_id');
    }
}

class MarketingSolutionPage extends Model
{
    protected $table = 'marketing_solution_pages';

    protected $fillable = ['industry_id', 'slug', 'title', 'body', 'published'];

    protected $casts = ['published' => 'boolean'];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(MarketingSolutionIndustry::class, 'industry_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true);
    }
}
