<?php

namespace Modules\SiteBuilder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Platform\Support\TenantAliasDomains;

class WebinoSiteDomain extends Model
{
    public const ROLE_PRIMARY = 'primary';

    public const ROLE_ALIAS = 'alias';

    public const ROLE_REDIRECT = 'redirect';

    public const ROLES = [self::ROLE_PRIMARY, self::ROLE_ALIAS, self::ROLE_REDIRECT];

    protected $table = 'webino_site_domains';

    protected $fillable = ['provision_id', 'domain', 'role', 'include_www', 'redirect_path', 'redirect_status', 'preserve_path'];

    protected $casts = [
        'include_www' => 'boolean',
        'preserve_path' => 'boolean',
        'redirect_status' => 'integer',
    ];

    public function provision(): BelongsTo
    {
        return $this->belongsTo(WebinoSiteProvision::class, 'provision_id');
    }

    /**
     * Keep the primary row in sync with provisions.domain (source of truth for primary).
     */
    public static function ensurePrimary(WebinoSiteProvision $provision): ?self
    {
        $primary = TenantAliasDomains::normalizeHost((string) $provision->domain);
        if ($primary === '' || ! str_contains($primary, '.')) {
            return null;
        }
        $existing = static::query()->where('domain', $primary)->first();
        if ($existing && (int) $existing->provision_id !== (int) $provision->id) {
            return null; // owned by another site; never steal it
        }
        static::query()->where('provision_id', $provision->id)->where('role', self::ROLE_PRIMARY)
            ->where('domain', '!=', $primary)->update(['role' => self::ROLE_ALIAS]);
        if ($existing) {
            if ($existing->role !== self::ROLE_PRIMARY) {
                $existing->update(['role' => self::ROLE_PRIMARY]);
            }

            return $existing;
        }

        return static::query()->create([
            'provision_id' => $provision->id, 'domain' => $primary, 'role' => self::ROLE_PRIMARY, 'include_www' => true,
        ]);
    }
}
