<?php

namespace Modules\Marketing\Services;

/**
 * Default, editable content for the corporate theme (homepage blocks, header, footer,
 * contact and social links). Values come from Database/Seeders/data/webina-site-defaults.json.
 *
 * apply() only fills what is missing: existing CMS edits are never overwritten.
 */
class SiteContentDefaults
{
    public static function load(): array
    {
        $path = dirname(__DIR__).'/Database/Seeders/data/webina-site-defaults.json';
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @return array{0: array, 1: array, 2: array} [home_blocks, branding, social_links]
     */
    public static function apply(?array $blocks, ?array $branding, mixed $social): array
    {
        $defaults = self::load();
        $blocks = is_array($blocks) ? array_values($blocks) : [];
        $branding = is_array($branding) ? $branding : [];

        $hasProps = false;
        foreach ($blocks as $block) {
            if (! empty($block['props'])) {
                $hasProps = true;
                break;
            }
        }

        $defaultBlocks = $defaults['home_blocks'] ?? [];
        if (! $hasProps) {
            // First run of the editable homepage: lay blocks out in the brief's order and keep
            // each block's on/off switch. Blocks this theme doesn't render stay at the end.
            $enabled = [];
            foreach ($blocks as $block) {
                $enabled[$block['type'] ?? ''] = $block['enabled'] ?? true;
            }
            $known = [];
            $ordered = [];
            foreach ($defaultBlocks as $block) {
                $known[$block['type']] = true;
                $block['enabled'] = (bool) ($enabled[$block['type']] ?? true);
                $ordered[] = $block;
            }
            foreach ($blocks as $block) {
                if (! isset($known[$block['type'] ?? ''])) {
                    $ordered[] = $block;
                }
            }
            $blocks = $ordered;
        } else {
            $index = [];
            foreach ($blocks as $i => $block) {
                $index[$block['type'] ?? ''] = $i;
            }
            foreach ($defaultBlocks as $block) {
                $i = $index[$block['type']] ?? null;
                if ($i === null) {
                    $blocks[] = $block;

                    continue;
                }
                $props = is_array($blocks[$i]['props'] ?? null) ? $blocks[$i]['props'] : [];
                $blocks[$i]['props'] = $props + $block['props'];
            }
        }

        foreach (['header', 'footer', 'contact'] as $key) {
            $current = is_array($branding[$key] ?? null) ? $branding[$key] : [];
            $branding[$key] = $current + ($defaults[$key] ?? []);
        }

        $social = is_array($social) && $social !== [] ? $social : ($defaults['social_links'] ?? []);

        return [$blocks, $branding, $social];
    }
}
