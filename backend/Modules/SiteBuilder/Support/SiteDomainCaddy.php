<?php

namespace Modules\SiteBuilder\Support;

use Modules\Platform\Support\TenantAliasDomains;
use Modules\Platform\Support\TenantSiteStack;

/**
 * Render a tenant Caddy snippet from webino_site_domains rows.
 * Serving block = primary + aliases (+ www variants) + derived aliases (contact/legacy).
 * Each redirect domain gets its own redir block to the primary.
 */
final class SiteDomainCaddy
{
    /**
     * @param  iterable<array{domain:string,role:string,include_www?:bool,redirect_path?:?string,redirect_status?:?int,preserve_path?:bool}>  $domains
     * @param  list<string>  $derivedAliases
     */
    public static function render(string $primary, string $slug, iterable $domains, array $derivedAliases = []): string
    {
        $primary = TenantAliasDomains::normalizeHost($primary);
        $serve = [];
        $redirects = [];
        $primaryWww = false;
        $wwwCanon = [];
        foreach ($domains as $d) {
            $d = (array) $d;
            $host = TenantAliasDomains::normalizeHost((string) ($d['domain'] ?? ''));
            if ($host === '' || ! str_contains($host, '.')) {
                continue;
            }
            $www = (bool) ($d['include_www'] ?? false);
            if (($d['role'] ?? '') === 'redirect') {
                $redirects[] = [$host, $www, self::normalizePath($d['redirect_path'] ?? null), self::status($d['redirect_status'] ?? 301), (bool) ($d['preserve_path'] ?? true)];

                continue;
            }
            if ($host === $primary) {
                $primaryWww = $www;

                continue;
            }
            $serve[] = $host;
            if ($www) {
                $wwwCanon[] = $host;
            }
        }
        foreach ($derivedAliases as $a) {
            $serve[] = $a;
        }
        $redirectHosts = [];
        foreach ($redirects as [$h, $w]) {
            $redirectHosts[] = $h;
            if ($w) {
                $redirectHosts[] = 'www.'.$h;
            }
        }
        $serve = array_values(array_diff(array_unique($serve), $redirectHosts, [$primary]));

        $out = TenantSiteStack::caddySnippet($primary, $slug, $serve);
        if ($primaryWww) {
            array_unshift($wwwCanon, $primary);
        }
        foreach (array_unique($wwwCanon) as $h) {
            // www variant: canonical redirect to the bare host.
            $out .= "\n\nwww.{$h} {\n  redir https://{$h}{uri} 301\n}";
        }
        foreach ($redirects as [$h, $w, $path, $status, $keep]) {
            $hosts = $w ? "{$h}, www.{$h}" : $h;
            $target = 'https://'.$primary.($path === '/' ? '' : $path).($keep ? '{uri}' : ($path === '/' ? '/' : ''));
            $out .= "\n\n{$hosts} {\n  redir {$target} {$status}\n}";
        }

        return $out."\n";
    }

    public static function normalizePath(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '' || $path === '/') {
            return '/';
        }
        $path = '/'.ltrim($path, '/');
        $path = preg_replace('#[^A-Za-z0-9/_\-.~%]#', '', $path) ?? '/';

        return rtrim($path, '/') ?: '/';
    }

    public static function status(mixed $status): int
    {
        $s = (int) $status;

        return in_array($s, [301, 302, 307, 308], true) ? $s : 301;
    }
}
