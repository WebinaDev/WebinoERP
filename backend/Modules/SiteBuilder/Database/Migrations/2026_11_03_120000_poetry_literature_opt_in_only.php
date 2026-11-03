<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «شعر و ادبیات» must never be default-on for site types/packages.
 * Keep explicit ERP enablement possible (addon + selected_feature_slugs).
 * Does not strip tenant-side enablement for sites that already selected it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webino_dashboard_features')) {
            return;
        }

        $rows = DB::table('webino_dashboard_features')->get(['id', 'slug', 'name_fa', 'name_en', 'module_slug']);
        $featureIds = [];

        foreach ($rows as $row) {
            $slug = strtolower((string) $row->slug);
            $module = strtolower((string) ($row->module_slug ?? ''));
            $nameFa = (string) $row->name_fa;
            $nameEn = strtolower((string) $row->name_en);

            $looksPoetry = in_array($slug, ['poetry', 'literature', 'poetry_literature'], true)
                || in_array($module, ['poetry', 'literature', 'poetry_literature'], true)
                || str_contains($nameFa, 'شعر')
                || str_contains($nameFa, 'ادبیات')
                || str_contains($nameEn, 'poetry')
                || str_contains($nameEn, 'literature');

            if (! $looksPoetry) {
                continue;
            }

            // Never demote core content modules that were merely mis-labeled.
            if (in_array($slug, ['blog', 'magazine', 'cms', 'academy', 'commerce', 'cafe', 'resume', 'corporate'], true)) {
                if ($slug === 'blog') {
                    DB::table('webino_dashboard_features')->where('id', $row->id)->update([
                        'name_fa' => 'وبلاگ',
                        'name_en' => 'Blog',
                        'updated_at' => now(),
                    ]);
                } elseif ($slug === 'magazine') {
                    DB::table('webino_dashboard_features')->where('id', $row->id)->update([
                        'name_fa' => 'مجله',
                        'name_en' => 'Magazine',
                        'updated_at' => now(),
                    ]);
                }
                continue;
            }

            $featureIds[] = (int) $row->id;
        }

        if ($featureIds === []) {
            if (! DB::table('webino_dashboard_features')->where('slug', 'poetry')->exists()) {
                DB::table('webino_dashboard_features')->insert([
                    'slug' => 'poetry',
                    'name_fa' => 'شعر و ادبیات',
                    'name_en' => 'Poetry & Literature',
                    'module_slug' => 'poetry',
                    'is_addon' => true,
                    'default_enabled' => false,
                    'sort_order' => 13,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            foreach ($featureIds as $id) {
                $row = DB::table('webino_dashboard_features')->where('id', $id)->first();
                $moduleSlug = is_string($row->module_slug ?? null) && $row->module_slug !== ''
                    ? $row->module_slug
                    : 'poetry';

                DB::table('webino_dashboard_features')->where('id', $id)->update([
                    'is_addon' => true,
                    'default_enabled' => false,
                    'module_slug' => $moduleSlug,
                    'updated_at' => now(),
                ]);
            }

            // Drop from starter packages so wizard non-addon defaults cannot re-enable it.
            if (Schema::hasTable('webino_package_features')) {
                DB::table('webino_package_features')->whereIn('feature_id', $featureIds)->delete();
            }

            // Existing pivots: never required / never default-selected.
            if (Schema::hasTable('webino_business_type_features')) {
                DB::table('webino_business_type_features')
                    ->whereIn('feature_id', $featureIds)
                    ->update([
                        'is_required' => false,
                        'default_selected' => false,
                    ]);
            }
        }

        // Ensure canonical poetry feature is attachable on every business type as opt-in add-on.
        $poetryId = DB::table('webino_dashboard_features')->where('slug', 'poetry')->value('id');
        if ($poetryId && Schema::hasTable('webino_business_types') && Schema::hasTable('webino_business_type_features')) {
            $typeIds = DB::table('webino_business_types')->pluck('id');
            foreach ($typeIds as $typeId) {
                $exists = DB::table('webino_business_type_features')
                    ->where('business_type_id', $typeId)
                    ->where('feature_id', $poetryId)
                    ->exists();
                if ($exists) {
                    DB::table('webino_business_type_features')
                        ->where('business_type_id', $typeId)
                        ->where('feature_id', $poetryId)
                        ->update(['is_required' => false, 'default_selected' => false]);
                } else {
                    DB::table('webino_business_type_features')->insert([
                        'business_type_id' => $typeId,
                        'feature_id' => $poetryId,
                        'is_required' => false,
                        'default_selected' => false,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: opt-in posture is the desired steady state.
    }
};
