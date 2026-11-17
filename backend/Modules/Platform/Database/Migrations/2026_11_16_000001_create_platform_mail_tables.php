<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول دامنه‌های ایمیل سازمانی و اعتبارسنجی DNS
        if (! Schema::hasTable('platform_mail_domains')) {
            Schema::create('platform_mail_domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('domain_id')->nullable()->constrained('platform_domains')->nullOnDelete();
                $table->string('domain_name', 191)->unique();
                $table->string('mx_record', 191)->nullable();
                $table->text('spf_record')->nullable();
                $table->text('dkim_selector')->nullable();
                $table->text('dkim_public_key')->nullable();
                $table->text('dkim_private_key')->nullable();
                $table->text('dmarc_record')->nullable();
                $table->string('dns_status', 30)->default('pending'); // verified, pending, failed
                $table->boolean('is_active')->default(true);
                $table->integer('max_mailboxes')->default(10);
                $table->bigInteger('quota_bytes_total')->default(10737418240); // 10 GB
                $table->timestamp('last_dns_checked_at')->nullable();
                $table->timestamps();
            });
        }

        // 2. جدول صندوق‌های پستی سازمانی (Mailboxes per domain)
        if (! Schema::hasTable('platform_mailboxes')) {
            Schema::create('platform_mailboxes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_domain_id')->constrained('platform_mail_domains')->cascadeOnDelete();
                $table->string('email_address', 191)->unique();
                $table->string('local_part', 100); // e.g. info, sales, support
                $table->string('display_name', 150)->nullable();
                $table->string('password_hash', 255);
                $table->bigInteger('quota_bytes')->default(1073741824); // 1 GB per mailbox
                $table->bigInteger('used_bytes')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_catch_all')->default(false);
                $table->string('auto_reply_subject', 191)->nullable();
                $table->text('auto_reply_body')->nullable();
                $table->boolean('auto_reply_active')->default(false);
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();

                $table->index(['mail_domain_id', 'is_active']);
            });
        }

        // 3. جدول نام‌های مستعار و فورواردینگ ایمیل (Mail Aliases & Forwards)
        if (! Schema::hasTable('platform_mail_aliases')) {
            Schema::create('platform_mail_aliases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_domain_id')->constrained('platform_mail_domains')->cascadeOnDelete();
                $table->string('source_email', 191);
                $table->string('destination_email', 191);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. جدول پیام‌های وب‌میل داخلی (Webmail Storage & Dispatch)
        if (! Schema::hasTable('platform_webmail_messages')) {
            Schema::create('platform_webmail_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mailbox_id')->constrained('platform_mailboxes')->cascadeOnDelete();
                $table->string('folder', 30)->default('inbox'); // inbox, sent, drafts, trash, spam
                $table->string('message_id', 191)->nullable()->index();
                $table->string('sender', 191);
                $table->string('sender_name', 150)->nullable();
                $table->text('recipients'); // JSON array of to/cc/bcc
                $table->string('subject', 255)->nullable();
                $table->longText('body_text')->nullable();
                $table->longText('body_html')->nullable();
                $table->json('attachments')->nullable();
                $table->boolean('is_read')->default(false);
                $table->boolean('is_starred')->default(false);
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index(['mailbox_id', 'folder', 'is_read']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_webmail_messages');
        Schema::dropIfExists('platform_mail_aliases');
        Schema::dropIfExists('platform_mailboxes');
        Schema::dropIfExists('platform_mail_domains');
    }
};
