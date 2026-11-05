<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\CoreLicense;

/**
 * Reads IPPanel pattern titles and rewrites them to
 * «site | role | action», then restores the local registry.
 */
class ModirPayamakPatternSync
{
    public function __construct(
        private ModirPayamakEdgeClient $edge,
        private ModirPayamakManager $manager,
    ) {}

    public function reconcileCached(string $domain): void
    {
        $domain = $this->manager->normalizeDomain($domain);
        if ($domain === '') {
            return;
        }
        $key = 'modirpayamak:pattern-reconcile:global';
        $empty = Schema::hasTable('modirpayamak_pattern_registry')
            && ! \Modules\Integrations\Entities\ModirPayamakPatternRegistry::query()->where('domain', $domain)->exists();
        if (! $empty && Cache::has($key)) {
            return;
        }
        if (! config('integrations.modirpayamak.mock', false) && ! $this->edge->isConfigured()) {
            return;
        }
        $this->reconcile();
        Cache::put($key, 1, now()->addMinutes(10));
    }

    /**
     * @return array{updated: int, bound: int, skipped: int}
     */
    public function reconcile(): array
    {
        $stats = ['updated' => 0, 'bound' => 0, 'skipped' => 0];
        $listed = $this->edge->listPatterns(['per_page' => 200, 'page' => 1]);
        if (empty($listed['ok'])) {
            return $stats;
        }
        $patterns = ModirPayamakReportRows::extract($listed['data'] ?? null);
        $sites = $this->sites();

        foreach ($patterns as $pattern) {
            if (! is_array($pattern)) {
                $stats['skipped']++;
                continue;
            }
            $code = $this->code($pattern);
            $title = $this->text($pattern, ['title', 'pattern_title', 'name']);
            $message = $this->text($pattern, ['message', 'pattern_message', 'text', 'body']);
            $parsed = ModirPayamakPatternCatalog::parseTitle($title);
            if ($code === '' || $parsed === null) {
                $stats['skipped']++;
                continue;
            }
            $site = $this->matchSite($parsed['site'], $sites);
            if ($site === null) {
                $stats['skipped']++;
                continue;
            }
            $eventKey = $parsed['event_key'];
            $scope = in_array($eventKey, ModirPayamakManager::SITE_EVENTS, true)
                ? 'site'
                : ModirPayamakPatternCatalog::scopeForRole($parsed['role']);
            $canonical = ModirPayamakPatternCatalog::title($site['name'] !== '' ? $site['name'] : $site['domain'], $parsed['role'], $parsed['action']);
            $variables = ModirPayamakPatternCatalog::variablesFromMessage($message);
            $website = 'https://'.$site['domain'];
            if ($title !== $canonical && $message !== '') {
                $updated = $this->edge->updatePattern($code, [
                    'title' => $canonical,
                    'description' => $canonical,
                    'message' => $message,
                    'website' => $website,
                    'is_share' => false,
                    'variable' => $variables,
                ]);
                if (! empty($updated['ok'])) {
                    $stats['updated']++;
                }
            }
            $map = [];
            foreach ($variables as $variable) {
                $map[$variable['name']] = $variable['name'];
            }
            $this->manager->attachPattern($site['domain'], $scope, $eventKey, $code, $map !== [] ? $map : null);
            $stats['bound']++;
        }

        return $stats;
    }

    /**
     * @return list<array{domain: string, name: string}>
     */
    private function sites(): array
    {
        if (! Schema::hasTable('core_licenses')) {
            return [];
        }
        $out = [];
        foreach (CoreLicense::query()->get() as $license) {
            $domain = $this->manager->normalizeDomain((string) $license->domain);
            if ($domain === '') {
                continue;
            }
            $name = trim((string) ($license->project_name ?? ''));
            $out[] = ['domain' => $domain, 'name' => $name !== '' ? $name : $domain];
        }

        return $out;
    }

    /**
     * @param  list<array{domain: string, name: string}>  $sites
     * @return array{domain: string, name: string}|null
     */
    private function matchSite(string $siteName, array $sites): ?array
    {
        if (trim($siteName) === '') {
            return null;
        }
        $exact = null;
        $fuzzy = null;
        foreach ($sites as $site) {
            if (ModirPayamakPatternCatalog::fold($siteName) === ModirPayamakPatternCatalog::fold($site['name'])
                || ModirPayamakPatternCatalog::fold($siteName) === ModirPayamakPatternCatalog::fold($site['domain'])) {
                $exact = $site;
                break;
            }
            if ($fuzzy === null && (
                ModirPayamakPatternCatalog::namesMatch($siteName, $site['name'])
                || ModirPayamakPatternCatalog::namesMatch($siteName, $site['domain'])
            )) {
                $fuzzy = $site;
            }
        }

        return $exact ?? $fuzzy;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function code(array $row): string
    {
        foreach (['pattern_code', 'code', 'pattern_id'] as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function text(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_scalar($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }
}
