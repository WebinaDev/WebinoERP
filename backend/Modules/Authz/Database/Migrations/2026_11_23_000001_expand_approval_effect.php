<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['authz_role_permissions', 'authz_policies'] as $table) {
            $column = DB::selectOne(
                'SELECT character_maximum_length FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
                [$table, 'effect']
            );
            if ($column && $column->character_maximum_length !== null && $column->character_maximum_length < 19) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->string('effect', 32)->default('allow')->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Keep existing values intact.
    }
};
