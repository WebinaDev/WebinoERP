<?php

namespace Modules\Core\Services;

use InvalidArgumentException;
use Modules\Core\Entities\ModuleGitSource;

/**
 * Normalizes core_licenses.meta for CRM UI and license/check API (dashboard entitlement + git hints).
 *
 * Contract (stored JSON `meta`):
 * - `modules`: string[] — slug های ماژول مجاز (مثلاً dashboard، accounting).
 * - `vertical`: ?string — عمود کسب‌وکار اختیاری.
 * - `sku`: ?string — شناسهٔ SKU/بسته اختیاری (در پاسخ license/check برمی‌گردد؛ secret نیست).
 * - `module_repos`: list<{ slug, repo_url|clone_url, deploy_token_ref? }> — repo به‌ازای ماژول؛ فقط ref توکن در DB، نه مقدار خام.
 * - `git`: { module_slug, repo_url, deploy_token_ref? } — شکل تکی جایگزین برای یک ماژول.
 *
 * پاسخ عمومی `/license/check` هرگز deploy_token یا PAT واقعی برنمی‌گرداند؛ فقط map slug→clone URL و entitlements.
 */
class CoreLicenseMetaNormalizer
{
    /** @var list<string> */
    public const ALLOWED_META_KEYS = [
        'modules', 'vertical', 'sku', 'module_repos', 'git',
        'business_category', 'business_type', 'features', 'theme_preset', 'nav_preset',
    ];

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array{
     *   licensed_modules: string[],
     *   vertical: ?string,
     *   sku: ?string,
     *   business_category: ?string,
     *   business_type: ?string,
     *   features: string[],
     *   theme_preset: ?string,
     *   nav_preset: ?array<string, mixed>,
     *   module_git_repos: array<string, string>,
     *   raw: array<string, mixed>
     * }
     */
    public static function normalize(?array $meta): array
    {
        $meta = is_array($meta) ? $meta : [];

        $modules = $meta['modules'] ?? $meta['licensed_modules'] ?? null;
        $licensed = [];
        if (is_array($modules)) {
            foreach ($modules as $item) {
                if (is_string($item) && $item !== '') {
                    $licensed[] = $item;

                    continue;
                }
                if (is_array($item)) {
                    $slug = $item['slug'] ?? $item['module'] ?? null;
                    if (is_string($slug) && $slug !== '') {
                        $licensed[] = $slug;
                    }
                }
            }
        }
        $licensed = array_values(array_unique($licensed));

        $vertical = isset($meta['vertical']) && is_string($meta['vertical'])
            ? $meta['vertical']
            : null;

        $sku = isset($meta['sku']) && is_string($meta['sku']) && $meta['sku'] !== ''
            ? $meta['sku']
            : null;

        $repos = self::extractGitRepos($meta);

        $businessCategory = isset($meta['business_category']) && is_string($meta['business_category'])
            ? $meta['business_category']
            : null;

        $businessType = isset($meta['business_type']) && is_string($meta['business_type'])
            ? $meta['business_type']
            : null;

        $features = [];
        if (isset($meta['features']) && is_array($meta['features'])) {
            foreach ($meta['features'] as $f) {
                if (is_string($f) && $f !== '') {
                    $features[] = $f;
                }
            }
        }
        $features = array_values(array_unique($features));

        $themePreset = isset($meta['theme_preset']) && is_string($meta['theme_preset'])
            ? $meta['theme_preset']
            : null;

        $navPreset = isset($meta['nav_preset']) && is_array($meta['nav_preset'])
            ? $meta['nav_preset']
            : null;

        return [
            'licensed_modules' => $licensed,
            'vertical' => $vertical,
            'sku' => $sku,
            'business_category' => $businessCategory,
            'business_type' => $businessType,
            'features' => $features,
            'theme_preset' => $themePreset,
            'nav_preset' => $navPreset,
            'module_git_repos' => $repos,
            'raw' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string> slug => clone_url
     */
    protected static function extractGitRepos(array $meta): array
    {
        $out = [];

        if (! empty($meta['module_repos']) && is_array($meta['module_repos'])) {
            foreach ($meta['module_repos'] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $slug = $entry['slug'] ?? $entry['module_slug'] ?? null;
                $url = $entry['repo_url'] ?? $entry['clone_url'] ?? null;
                if (is_string($slug) && $slug !== '' && is_string($url) && $url !== '') {
                    $out[$slug] = $url;
                }
            }
        }

        $git = $meta['git'] ?? null;
        if (is_array($git)) {
            $slug = $git['module_slug'] ?? null;
            $url = $git['repo_url'] ?? null;
            if (is_string($slug) && $slug !== '' && is_string($url) && $url !== '') {
                $out[$slug] = $url;
            }
        }

        return $out;
    }

    /**
     * Validate meta shape for API input (subset rules).
     *
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>|null  sanitized meta or null
     *
     * @throws InvalidArgumentException when unknown top-level keys are present
     */
    public static function validateForStorage(?array $meta): ?array
    {
        if ($meta === null) {
            return null;
        }

        foreach (array_keys($meta) as $key) {
            if (! is_string($key) || ! in_array($key, self::ALLOWED_META_KEYS, true)) {
                throw new InvalidArgumentException('Unknown meta key: '.$key);
            }
        }

        $clean = [];

        if (isset($meta['modules'])) {
            $mods = [];
            if (is_array($meta['modules'])) {
                foreach ($meta['modules'] as $s) {
                    if (is_string($s) && preg_match('/^[a-z0-9_]{1,64}$/', $s)) {
                        $mods[] = $s;
                    }
                }
            }
            $clean['modules'] = array_values(array_unique($mods));
        }

        if (isset($meta['vertical'])) {
            $clean['vertical'] = is_string($meta['vertical']) && strlen($meta['vertical']) <= 64
                ? $meta['vertical']
                : null;
        }

        if (array_key_exists('sku', $meta)) {
            $s = $meta['sku'];
            if ($s === null || $s === '') {
                /* omit sku */
            } elseif (is_string($s) && preg_match('/^[a-zA-Z0-9._-]{1,128}$/', $s)) {
                $clean['sku'] = $s;
            } else {
                throw new InvalidArgumentException('Invalid meta.sku');
            }
        }

        if (isset($meta['module_repos']) && is_array($meta['module_repos'])) {
            $repos = [];
            foreach ($meta['module_repos'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $slug = $row['slug'] ?? $row['module_slug'] ?? '';
                $url = $row['repo_url'] ?? $row['clone_url'] ?? '';
                $ref = $row['deploy_token_ref'] ?? null;
                if (! is_string($slug) || ! preg_match('/^[a-z0-9_]{1,64}$/', $slug)) {
                    continue;
                }
                if (! is_string($url) || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $entry = ['slug' => $slug, 'repo_url' => $url];
                if (is_string($ref) && $ref !== '' && self::validDeployTokenRef($ref)) {
                    $entry['deploy_token_ref'] = $ref;
                }
                $repos[] = $entry;
            }
            $clean['module_repos'] = $repos;
        }

        if (isset($meta['git']) && is_array($meta['git'])) {
            $g = $meta['git'];
            $slug = $g['module_slug'] ?? '';
            $url = $g['repo_url'] ?? '';
            if (is_string($slug) && preg_match('/^[a-z0-9_]{1,64}$/', $slug)
                && is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $clean['git'] = ['module_slug' => $slug, 'repo_url' => $url];
                $ref = $g['deploy_token_ref'] ?? null;
                if (is_string($ref) && $ref !== '' && self::validDeployTokenRef($ref)) {
                    $clean['git']['deploy_token_ref'] = $ref;
                }
            }
        }

        if (isset($meta['business_category'])) {
            $clean['business_category'] = is_string($meta['business_category']) && strlen($meta['business_category']) <= 64
                ? $meta['business_category']
                : null;
        }

        if (isset($meta['business_type'])) {
            $clean['business_type'] = is_string($meta['business_type']) && strlen($meta['business_type']) <= 64
                ? $meta['business_type']
                : null;
        }

        if (isset($meta['features']) && is_array($meta['features'])) {
            $feats = [];
            foreach ($meta['features'] as $f) {
                if (is_string($f) && preg_match('/^[a-z0-9_]{1,64}$/', $f)) {
                    $feats[] = $f;
                }
            }
            $clean['features'] = array_values(array_unique($feats));
        }

        if (isset($meta['theme_preset'])) {
            $clean['theme_preset'] = is_string($meta['theme_preset']) && strlen($meta['theme_preset']) <= 64
                ? $meta['theme_preset']
                : null;
        }

        if (isset($meta['nav_preset']) && is_array($meta['nav_preset'])) {
            $clean['nav_preset'] = $meta['nav_preset'];
        }

        return count($clean) > 0 ? $clean : [];
    }

    protected static function validDeployTokenRef(string $ref): bool
    {
        return strlen($ref) <= 255 && preg_match('/^[a-zA-Z0-9_:.@-]+$/', $ref) === 1;
    }

    /**
     * Registry URLs override per-license meta (central admin source of truth).
     *
     * @param  array<string, string>  $fromMeta
     * @return array<string, string>
     */
    public static function mergeModuleGitReposWithRegistry(array $fromMeta): array
    {
        $out = $fromMeta;
        try {
            $rows = ModuleGitSource::query()
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->pluck('clone_url', 'slug');
            foreach ($rows as $slug => $url) {
                if (is_string($slug) && $slug !== '' && is_string($url) && $url !== '') {
                    $out[$slug] = $url;
                }
            }
        } catch (\Throwable) {
            /* migrations not run yet */
        }

        return $out;
    }

    public static function forgetCheckCache(?string $domain, ?string $licenseKey): void
    {
        if ($domain === null || $domain === '') {
            return;
        }
        $key = 'license_check:'.md5($domain.'|'.$licenseKey);

        \Illuminate\Support\Facades\Cache::forget($key);
    }
}
