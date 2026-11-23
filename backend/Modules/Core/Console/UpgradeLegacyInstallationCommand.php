<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Support\Initialization\RolesAndPermissionsCatalog;
use Modules\SiteBuilder\Support\Initialization\LegacySiteTypeUpgrade;

final class UpgradeLegacyInstallationCommand extends Command
{
    protected $signature = 'core:upgrade-legacy-installation';

    protected $description = 'Apply pending legacy metadata upgrades';

    public function handle(): int
    {
        if (! Schema::hasTable('core_legacy_upgrades')) {
            return self::SUCCESS;
        }

        foreach (['roles', 'site_types'] as $name) {
            DB::transaction(function () use ($name): void {
                $upgrade = DB::table('core_legacy_upgrades')->where('name', $name)->lockForUpdate()->first();
                if (! $upgrade || $upgrade->completed_at !== null) {
                    return;
                }

                match ($name) {
                    'roles' => app(RolesAndPermissionsCatalog::class)->upgradeMissingRoles(),
                    'site_types' => app(LegacySiteTypeUpgrade::class)->run(),
                };

                DB::table('core_legacy_upgrades')->where('name', $name)->update(['completed_at' => now()]);
            });
        }

        return self::SUCCESS;
    }
}
