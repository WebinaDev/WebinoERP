<?php

namespace Modules\Core\Support\Initialization;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LegacyUpgradeQueue
{
    public static function record(string $upgrade): void
    {
        if (! Schema::hasTable('users') || ! DB::table('users')->exists()) {
            return;
        }

        if (! Schema::hasTable('core_legacy_upgrades')) {
            Schema::create('core_legacy_upgrades', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->timestamp('completed_at')->nullable();
            });
        }

        DB::table('core_legacy_upgrades')->insertOrIgnore(['name' => $upgrade]);
    }
}
