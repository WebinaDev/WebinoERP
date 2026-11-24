<?php

namespace Modules\Marketing\Support;

/**
 * Rewrites legacy builder widget type aliases inside saved builder documents.
 * Only the "type" key of nodes is touched; everything else is preserved verbatim.
 */
class BuilderWidgetAliases
{
    /**
     * Vendored copy of @webina/ui src/builder/data/widget-aliases.json (shared with WebinoDashboard).
     * Sync with: cp <webina-ui>/src/builder/data/widget-aliases.json backend/Modules/Marketing/Resources/builder/
     */
    public const JSON_PATH = __DIR__.'/../Resources/builder/widget-aliases.json';

    private static ?array $map = null;

    /** @return array<string, string> legacy type => current type */
    public static function map(): array
    {
        if (self::$map === null) {
            $data = json_decode((string) @file_get_contents(self::JSON_PATH), true);
            $aliases = is_array($data['aliases'] ?? null) ? $data['aliases'] : [];
            self::$map = array_filter($aliases, fn ($v, $k) => is_string($k) && is_string($v), ARRAY_FILTER_USE_BOTH);
        }

        return self::$map;
    }

    /**
     * @return array{0: mixed, 1: int} rewritten document and number of replaced nodes
     */
    public static function migrate(mixed $doc): array
    {
        $count = 0;
        $map = self::map();
        $walkSections = function (mixed $sections) use (&$walkSections, &$count, $map): mixed {
            if (! is_array($sections)) {
                return $sections;
            }
            foreach ($sections as $sectionIndex => $section) {
                if (! is_array($section) || ! is_array($section['columns'] ?? null)) {
                    continue;
                }
                foreach ($section['columns'] as $columnIndex => $column) {
                    if (! is_array($column) || ! is_array($column['widgets'] ?? null)) {
                        continue;
                    }
                    foreach ($column['widgets'] as $widgetIndex => $widget) {
                        if (! is_array($widget)) {
                            continue;
                        }
                        $type = $widget['type'] ?? null;
                        if (is_string($type) && isset($map[$type])) {
                            $widget['type'] = $map[$type];
                            $count++;
                        }
                        if (array_key_exists('children', $widget)) {
                            $widget['children'] = $walkSections($widget['children']);
                        }
                        $section['columns'][$columnIndex]['widgets'][$widgetIndex] = $widget;
                    }
                }
                $sections[$sectionIndex] = $section;
            }

            return $sections;
        };

        if (is_array($doc) && array_key_exists('sections', $doc)) {
            $doc['sections'] = $walkSections($doc['sections']);
        }

        return [$doc, $count];
    }
}
