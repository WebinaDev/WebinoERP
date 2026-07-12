<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prj_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_contracts', 'notes')) {
                $table->text('notes')->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prj_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('prj_contracts', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
