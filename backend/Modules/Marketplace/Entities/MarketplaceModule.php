<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Core\Entities\ModuleGitSource;

class MarketplaceModule extends Model
{
    protected $table = 'marketplace_modules';

    protected $fillable = [
        'name',
        'slug',
        'distribution',
        'description',
        'icon_url',
        'detail_url',
        'settings_route',
        'settings_area',
        'readme_md',
        'version',
        'status',
        'category_id',
        'parent_module_id',
        'price',
        'currency',
        'sort',
        'requires_license',
        'is_core',
        'is_builtin',
        'is_free',
        'module_git_source_id',
        'gitea_owner',
        'gitea_repo',
        'gitea_repo_id',
        'package_path',
        'package_source',
        'latest_release_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'requires_license' => 'boolean',
        'is_core' => 'boolean',
        'is_builtin' => 'boolean',
        'is_free' => 'boolean',
        'sort' => 'integer',
        'gitea_repo_id' => 'integer',
        'latest_release_id' => 'integer',
        'parent_module_id' => 'integer',
    ];

    public function repo(): HasOne
    {
        return $this->hasOne(MarketplaceModuleRepo::class, 'module_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(MarketplaceRelease::class, 'module_id');
    }

    public function gitSource(): BelongsTo
    {
        return $this->belongsTo(ModuleGitSource::class, 'module_git_source_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_module_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_module_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(MarketplaceEntitlement::class, 'module_id');
    }

    public function isGitDistributed(): bool
    {
        return ($this->distribution ?? 'git') === 'git';
    }
}
