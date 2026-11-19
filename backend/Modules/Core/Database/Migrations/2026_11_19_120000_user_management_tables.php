<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User management: account controls on users, personal profile, private person documents
 * and per-user notification channel/category switches. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 60)->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false);
            }
            if (! Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable();
            }
            if (! Schema::hasColumn('users', 'lock_reason')) {
                $table->string('lock_reason', 500)->nullable();
            }
            if (! Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable();
            }
        });

        if (! Schema::hasTable('core_user_profiles')) {
            Schema::create('core_user_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('national_id', 20)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('gender', 20)->nullable();
                $table->string('father_name', 100)->nullable();
                $table->string('phone_alt', 30)->nullable();
                $table->string('landline', 30)->nullable();
                $table->string('province', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('address', 1000)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('job_title', 150)->nullable();
                $table->string('company', 191)->nullable();
                $table->string('emergency_contact_name', 150)->nullable();
                $table->string('emergency_contact_phone', 30)->nullable();
                $table->text('bio')->nullable();
                $table->text('admin_notes')->nullable();
                $table->json('extra')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('core_user_documents')) {
            Schema::create('core_user_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 40)->default('other');
                $table->string('title', 191);
                $table->string('number', 100)->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable()->index();
                $table->text('notes')->nullable();
                $table->string('disk', 40)->default('local');
                $table->string('path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->unsignedInteger('version')->default(1);
                $table->string('status', 20)->default('valid');
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['user_id', 'type']);
            });
        }

        if (! Schema::hasTable('user_notification_preferences')) {
            Schema::create('user_notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('event_key', 100);
                $table->boolean('channel_in_app')->default(true);
                $table->boolean('channel_sms')->default(true);
                $table->boolean('channel_email')->default(true);
                $table->boolean('channel_chat')->default(false);
                $table->time('quiet_hours_start')->nullable();
                $table->time('quiet_hours_end')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'event_key']);
            });
        }
        Schema::table('user_notification_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('user_notification_preferences', 'channel_push')) {
                $table->boolean('channel_push')->default(true);
            }
            if (! Schema::hasColumn('user_notification_preferences', 'enabled')) {
                $table->boolean('enabled')->default(true);
            }
        });
    }

    public function down(): void
    {
        // Additive migration: keep user data on rollback.
    }
};
