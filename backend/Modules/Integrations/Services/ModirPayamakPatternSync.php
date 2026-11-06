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
        $patterns = ModirPayamakReportRows::patterns($listed['data'] ?? null);
        usort($patterns, function (array $left, array $right): int {
            $leftTitle = $this->text($left, ['title', 'pattern_title', 'name']);
            $rightTitle = $this->text($right, ['title', 'pattern_title', 'name']);

            return (ModirPayamakPatternCatalog::parseTitle($rightTitle) !== null ? 1 : 0)
                <=> (ModirPayamakPatternCatalog::parseTitle($leftTitle) !== null ? 1 : 0);
        });
        $sites = $this->sites();
        $taken = [];

        foreach ($patterns as $pattern) {
            $code = $this->code($pattern);
            $title = $this->text($pattern, ['title', 'pattern_title', 'name']);
            $message = $this->text($pattern, ['pattern_message', 'message', 'text', 'body']);
            $website = $this->text($pattern, ['website', 'url', 'site']);
            $found = ModirPayamakPatternCatalog::identify($title, $message, $website, $sites);
            if ($code === '' || $found === null) {
                $stats['skipped']++;
                continue;
            }
            $eventKey = $found['event_key'];
            $scope = in_array($eventKey, ModirPayamakManager::SITE_EVENTS, true)
                ? 'site'
                : ModirPayamakPatternCatalog::scopeForRole($found['role']);
            $slot = $found['domain'].'|'.$scope.'|'.$eventKey;
            if (isset($taken[$slot])) {
                $stats['skipped']++;
                continue;
            }
            $canonical = ModirPayamakPatternCatalog::title($found['site'], $found['role'], $found['action']);
            $variables = ModirPayamakPatternCatalog::variablesFromMessage($message);
            $siteUrl = 'https://'.$found['domain'];
            if ($title !== $canonical && $message !== '') {
                $updated = $this->edge->updatePattern($code, [
                    'title' => $canonical,
                    'description' => $canonical,
                    'message' => $message,
                    'pattern_message' => $message,
                    'website' => $siteUrl,
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
            $this->manager->attachPattern($found['domain'], $scope, $eventKey, $code, $map !== [] ? $map : null);
            $taken[$slot] = $code;
            $stats['bound']++;
        }

        if ($stats['updated'] > 0) {
            Cache::forget('modirpayamak:pattern-bodies');
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
