<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoBusinessType;
use Modules\SiteBuilder\Entities\WebinoDashboardFeature;

/**
 * Adds an `icon` column to dashboard features and the «لینک‌هاب» (link-hub) add-on,
 * offered (not pre-selected) on every site type. Data-only; never re-provisions sites.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webino_dashboard_features')) {
            return;
        }
        if (! Schema::hasColumn('webino_dashboard_features', 'icon')) {
            Schema::table('webino_dashboard_features', function (Blueprint $table) {
                $table->string('icon', 64)->nullable()->after('module_slug');
            });
        }
        (new SiteBuilderSeeder)->run();

        $feature = WebinoDashboardFeature::query()->where('slug', 'link-hub')->first();
        if ($feature && Schema::hasTable('webino_business_types')) {
            WebinoBusinessType::query()->get()->each(fn ($t) => $t->features()->syncWithoutDetaching([
                $feature->id => ['is_required' => false, 'default_selected' => false],
            ]));
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
