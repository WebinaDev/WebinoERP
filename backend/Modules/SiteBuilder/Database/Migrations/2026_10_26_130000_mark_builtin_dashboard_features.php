<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;

/**
 * Built-in WebinoDashboard modules (no repo, activate-only):
 * - link-hub: core, required + selected on every site type.
 * - resume: optional add-on, off by default (kept selected only on the «رزومه» site type itself).
 * Catalog data only; existing sites are not touched or re-provisioned.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webino_dashboard_features')) {
            return;
        }
        if (! Schema::hasColumn('webino_dashboard_features', 'is_builtin')) {
            Schema::table('webino_dashboard_features', function (Blueprint $table) {
                $table->boolean('is_builtin')->default(false)->after('is_addon');
            });
        }
        (new SiteBuilderSeeder)->run();

        DB::table('webino_dashboard_features')->where('slug', 'link-hub')
            ->update(['is_builtin' => true, 'is_addon' => false, 'default_enabled' => true, 'is_active' => true]);
        DB::table('webino_dashboard_features')->where('slug', 'resume')
            ->update(['is_builtin' => true, 'is_addon' => true, 'default_enabled' => false]);

        if (! Schema::hasTable('webino_business_types')) {
            return;
        }
        $types = \Modules\SiteBuilder\Entities\WebinoBusinessType::query()->get();
        $hub = \Modules\SiteBuilder\Entities\WebinoDashboardFeature::query()->where('slug', 'link-hub')->first();
        $resume = \Modules\SiteBuilder\Entities\WebinoDashboardFeature::query()->where('slug', 'resume')->first();
        foreach ($types as $type) {
            if ($hub) {
                $type->features()->syncWithoutDetaching([$hub->id => ['is_required' => true, 'default_selected' => true]]);
            }
            if ($resume && $type->features()->whereKey($resume->id)->exists()) {
                $on = $type->slug === 'resume';
                $type->features()->updateExistingPivot($resume->id, ['is_required' => false, 'default_selected' => $on]);
            }
            $slugs = array_values(array_filter((array) ($type->default_module_slugs ?? []), 'is_string'));
            if (! in_array('link-hub', $slugs, true)) {
                $slugs[] = 'link-hub';
            }
            if ($type->slug !== 'resume') {
                $slugs = array_values(array_diff($slugs, ['resume']));
            }
            if ($slugs !== $type->default_module_slugs) {
                $type->default_module_slugs = $slugs;
                $type->saveQuietly();
            }
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
