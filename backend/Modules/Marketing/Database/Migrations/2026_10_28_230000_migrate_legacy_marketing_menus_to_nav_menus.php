<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the legacy «منوها» (marketing_menus + marketing_menu_items): copy every legacy menu into
 * marketing_nav_menus as slug `legacy-{location}` with a nested custom-link tree. Idempotent: a slug that
 * already exists is skipped, and a location is only assigned when no nav menu holds it yet. Legacy tables stay
 * (read-only) so nothing is lost; down() removes only the copies.
 */
return new class extends Migration
{
    private const LOCATION_MAP = [
        'header' => 'header_main', 'main' => 'header_main', 'primary' => 'header_main', 'header_main' => 'header_main',
        'top' => 'top_bar', 'top_bar' => 'top_bar', 'mobile' => 'mobile', 'mega' => 'mega_menu', 'mega_menu' => 'mega_menu',
        'footer' => 'footer_1', 'footer_1' => 'footer_1', 'footer_2' => 'footer_2', 'footer_3' => 'footer_3', 'footer_4' => 'footer_4',
        'footer_bottom' => 'footer_bottom', 'bottom' => 'footer_bottom',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('marketing_menus') || ! Schema::hasTable('marketing_menu_items') || ! Schema::hasTable('marketing_nav_menus')) {
            return;
        }
        $taken = [];
        foreach (DB::table('marketing_nav_menus')->pluck('locations') as $locs) {
            foreach ((array) json_decode((string) $locs, true) as $l) {
                $taken[$l] = true;
            }
        }
        foreach (DB::table('marketing_menus')->orderBy('id')->get() as $menu) {
            $slug = 'legacy-'.preg_replace('/[^\p{L}\p{N}_-]+/u', '-', (string) $menu->location);
            if (DB::table('marketing_nav_menus')->where('slug', $slug)->exists()) {
                continue;
            }
            $rows = DB::table('marketing_menu_items')->where('menu_id', $menu->id)->orderBy('sort_order')->orderBy('id')->get();
            $loc = self::LOCATION_MAP[strtolower((string) $menu->location)] ?? null;
            $locations = [];
            if ($loc && $menu->published && ! isset($taken[$loc])) {
                $locations[] = $loc;
                $taken[$loc] = true;
            }
            DB::table('marketing_nav_menus')->insert([
                'name' => mb_substr((string) $menu->name ?: 'منو', 0, 120),
                'slug' => mb_substr($slug, 0, 120),
                'items' => json_encode($this->tree($rows, null, 1), JSON_UNESCAPED_UNICODE),
                'locations' => json_encode($locations),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param  \Illuminate\Support\Collection<int, object>  $rows */
    private function tree($rows, ?int $parent, int $depth): array
    {
        if ($depth > 3) {
            return [];
        }
        $out = [];
        foreach ($rows->filter(fn ($r) => ($r->parent_id === null ? null : (int) $r->parent_id) === $parent) as $r) {
            $url = trim((string) $r->href) ?: '/';
            if (! preg_match('#^(/|https?://|tel:|mailto:|\#)#i', $url)) {
                $url = '/'.ltrim($url, '/');
            }
            $out[] = array_filter([
                'id' => 'legacy'.$r->id,
                'label' => mb_substr((string) $r->label, 0, 120),
                'type' => 'custom',
                'url' => preg_replace('#^/pages/#', '/', $url),
                'visibility' => $r->published ? null : 'hidden',
                'children' => $this->tree($rows, (int) $r->id, $depth + 1),
            ], fn ($v) => $v !== null);
        }

        return $out;
    }

    public function down(): void
    {
        if (Schema::hasTable('marketing_nav_menus')) {
            DB::table('marketing_nav_menus')->where('slug', 'like', 'legacy-%')->delete();
        }
    }
};
