<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_consultation_statuses')) {
            return;
        }
        if (DB::table('crm_consultation_statuses')->exists()) {
            return;
        }

        $now = now();
        DB::table('crm_consultation_statuses')->insert([
            ['name' => 'new', 'color' => '#64748b', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'open', 'color' => '#2563eb', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'pending', 'color' => '#d97706', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'done', 'color' => '#16a34a', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'converted', 'color' => '#7c3aed', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'cancelled', 'color' => '#b91c1c', 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_consultation_statuses')) {
            return;
        }

        DB::table('crm_consultation_statuses')->whereIn('name', [
            'new', 'open', 'pending', 'done', 'converted', 'cancelled',
        ])->delete();
    }
};
