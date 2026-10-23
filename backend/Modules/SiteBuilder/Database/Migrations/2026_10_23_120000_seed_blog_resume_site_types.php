<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;

/**
 * Adds the «blog» site type (business type + starter package) and makes sure «resume»
 * exists. SiteBuilderSeeder is create-only (firstOrCreate) so admin edits are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webino_business_types') || ! Schema::hasTable('webino_dashboard_features')) {
            return;
        }
        (new SiteBuilderSeeder)->run();
    }

    public function down(): void
    {
        // Data-only, non-destructive: keep the rows (licenses/provisions may reference them).
    }
};
