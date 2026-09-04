<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Entities\SystemModule;

/**
 * Fold Site Builder into Platform: deactivate site_builder module row and
 * ensure platform is active when site_builder was previously enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        $siteBuilder = SystemModule::query()->where('slug', 'site_builder')->first();
        $platform = SystemModule::query()->where('slug', 'platform')->first();

        if ($siteBuilder?->is_active) {
            if ($platform) {
                $platform->update(['is_active' => true]);
            } else {
                SystemModule::query()->create([
                    'name' => 'Platform',
                    'slug' => 'platform',
                    'is_active' => true,
                ]);
            }
        }

        if ($siteBuilder) {
            $siteBuilder->update(['is_active' => false]);
        } elseif (! $platform) {
            SystemModule::query()->firstOrCreate(
                ['slug' => 'platform'],
                ['name' => 'Platform', 'is_active' => true]
            );
        }
    }

    public function down(): void
    {
        SystemModule::query()->where('slug', 'site_builder')->update(['is_active' => true]);
    }
};
