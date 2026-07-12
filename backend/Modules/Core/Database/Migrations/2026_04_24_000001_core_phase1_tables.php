<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 parity foundation tables:
 *  - core_field_permissions  (parity: webinocrm class-field-security.php options)
 *  - core_cron_runs          (history for scheduled commands)
 *  - core_encryption_keys    (parity: webinocrm_encryption_key option + rotation)
 *
 * Also extends core_system_logs with `severity` and index updates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_field_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->string('field_name', 100);
            $table->json('view_roles')->nullable();
            $table->json('edit_roles')->nullable();
            $table->boolean('mask_view')->default(false);
            $table->string('mask_strategy', 32)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['entity_type', 'field_name']);
        });

        Schema::create('core_cron_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job', 100)->index();
            $table->string('status', 20)->default('ok');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
        });

        Schema::create('core_encryption_keys', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->string('key_cipher', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (Schema::hasTable('core_system_logs') && ! Schema::hasColumn('core_system_logs', 'severity')) {
            Schema::table('core_system_logs', function (Blueprint $table) {
                $table->unsignedTinyInteger('severity')->default(3)->after('level')->index();
                $table->string('error_code', 32)->nullable()->after('severity')->index();
                $table->string('ip', 45)->nullable()->after('channel');
                $table->text('user_agent')->nullable()->after('ip');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_encryption_keys');
        Schema::dropIfExists('core_cron_runs');
        Schema::dropIfExists('core_field_permissions');

        if (Schema::hasTable('core_system_logs') && Schema::hasColumn('core_system_logs', 'severity')) {
            Schema::table('core_system_logs', function (Blueprint $table) {
                $table->dropColumn(['severity', 'error_code', 'ip', 'user_agent']);
            });
        }
    }
};
