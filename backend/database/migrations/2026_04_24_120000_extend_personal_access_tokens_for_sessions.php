<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_access_tokens', 'device_name')) {
                $table->string('device_name', 120)->nullable()->after('name');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'ip')) {
                $table->string('ip', 45)->nullable()->after('token');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('last_used_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            foreach (['device_name', 'ip', 'user_agent', 'last_activity_at'] as $col) {
                if (Schema::hasColumn('personal_access_tokens', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
