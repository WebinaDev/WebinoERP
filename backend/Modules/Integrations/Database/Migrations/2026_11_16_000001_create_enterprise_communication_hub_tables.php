<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. کاتالوگ رویدادهای استاندارد ارتباطی سیستم (Event Catalog)
        if (! Schema::hasTable('communication_event_catalog')) {
            Schema::create('communication_event_catalog', function (Blueprint $table) {
                $table->id();
                $table->string('event_key', 100)->unique(); // e.g. order.paid, invoice.overdue, ticket.created
                $table->string('category', 50); // sales, crm, finance, hrm, platform
                $table->string('title_fa', 150);
                $table->string('title_en', 150);
                $table->string('default_priority', 20)->default('normal'); // critical, high, normal, low
                $table->json('allowed_channels'); // ['in_app', 'sms', 'email', 'chat']
                $table->boolean('is_transactional')->default(true); // تفکیک پیام‌های حیاتی تراکنشی از تبلیغاتی
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. رجیستری قالب‌های پیام به تفکیک کانال (Multi-Channel Template Registry)
        if (! Schema::hasTable('communication_templates')) {
            Schema::create('communication_templates', function (Blueprint $table) {
                $table->id();
                $table->string('event_key', 100)->index();
                $table->string('channel', 30); // in_app, sms, email, chat
                $table->string('subject', 255)->nullable();
                $table->text('body_template'); // پشتیبانی از متغیرهای {{customer_name}}, {{order_id}}, ...
                $table->string('sms_pattern_code', 50)->nullable(); // کد پترن پیامک خدماتی مدیرپیامک
                $table->boolean('is_default')->default(true);
                $table->timestamps();

                $table->unique(['event_key', 'channel']);
            });
        }

        // 3. ترجیحات و تنظیمات اعلان کاربر (User Notification Preferences & Quiet Hours)
        if (! Schema::hasTable('user_notification_preferences')) {
            Schema::create('user_notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('event_key', 100);
                $table->boolean('channel_in_app')->default(true);
                $table->boolean('channel_sms')->default(true);
                $table->boolean('channel_email')->default(true);
                $table->boolean('channel_chat')->default(false);
                $table->time('quiet_hours_start')->nullable(); // ساعات سکوت e.g. 23:00
                $table->time('quiet_hours_end')->nullable();   // e.g. 07:00
                $table->timestamps();

                $table->unique(['user_id', 'event_key']);
            });
        }

        // 4. بهینه‌سازی جدول دفتر کل ارسال (Channel Delivery Ledger)
        Schema::table('int_channel_deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('int_channel_deliveries', 'dedup_hash')) {
                $table->string('dedup_hash', 64)->nullable()->index()->after('meta');
            }
            if (! Schema::hasColumn('int_channel_deliveries', 'recipient')) {
                $table->string('recipient', 191)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('int_channel_deliveries', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('int_channel_deliveries', function (Blueprint $table) {
            $table->dropColumn(['dedup_hash', 'recipient', 'delivered_at']);
        });

        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('communication_templates');
        Schema::dropIfExists('communication_event_catalog');
    }
};
