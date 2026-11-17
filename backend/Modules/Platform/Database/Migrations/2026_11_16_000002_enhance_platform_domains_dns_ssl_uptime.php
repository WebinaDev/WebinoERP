<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ارتقای جدول دامنه‌ها برای اتصال به هاب مشتری، چرخه عمر SSL و پایش انقضا
        Schema::table('platform_domains', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_domains', 'crm_customer_id')) {
                $table->unsignedBigInteger('crm_customer_id')->nullable()->index()->after('resource_id');
            }
            if (! Schema::hasColumn('platform_domains', 'ssl_expires_at')) {
                $table->timestamp('ssl_expires_at')->nullable()->after('ssl_status');
            }
            if (! Schema::hasColumn('platform_domains', 'ssl_issuer')) {
                $table->string('ssl_issuer', 100)->nullable()->after('ssl_expires_at');
            }
            if (! Schema::hasColumn('platform_domains', 'ssl_auto_renew')) {
                $table->boolean('ssl_auto_renew')->default(true)->after('ssl_issuer');
            }
            if (! Schema::hasColumn('platform_domains', 'is_wildcard')) {
                $table->boolean('is_wildcard')->default(false)->after('ssl_auto_renew');
            }
            if (! Schema::hasColumn('platform_domains', 'last_uptime_status')) {
                $table->string('last_uptime_status', 30)->default('unknown')->after('is_wildcard'); // healthy, degraded, down
            }
            if (! Schema::hasColumn('platform_domains', 'last_uptime_checked_at')) {
                $table->timestamp('last_uptime_checked_at')->nullable()->after('last_uptime_status');
            }
        });

        // 2. جدول رکوردهای DNS کامل (Zone & Records Management: A, AAAA, CNAME, TXT, MX, SRV)
        if (! Schema::hasTable('platform_dns_records')) {
            Schema::create('platform_dns_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('domain_id')->constrained('platform_domains')->cascadeOnDelete();
                $table->string('type', 10); // A, AAAA, CNAME, TXT, MX, SRV, NS
                $table->string('name', 191); // @ or subdomain
                $table->text('value');
                $table->integer('ttl')->default(3600);
                $table->integer('priority')->nullable(); // for MX, SRV
                $table->boolean('is_proxied')->default(false); // Cloudflare/Arvan style
                $table->string('status', 30)->default('active'); // active, pending, error
                $table->timestamps();

                $table->index(['domain_id', 'type', 'name']);
            });
        }

        // 3. جدول لاگ‌های پایش سلامت و زمان فعالیت (Uptime & Health Checks)
        if (! Schema::hasTable('platform_uptime_checks')) {
            Schema::create('platform_uptime_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('domain_id')->constrained('platform_domains')->cascadeOnDelete();
                $table->string('url', 255);
                $table->integer('http_status')->nullable();
                $table->integer('response_time_ms')->nullable();
                $table->boolean('is_healthy')->default(true);
                $table->text('error_message')->nullable();
                $table->timestamp('checked_at');
                $table->timestamps();

                $table->index(['domain_id', 'checked_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_uptime_checks');
        Schema::dropIfExists('platform_dns_records');

        Schema::table('platform_domains', function (Blueprint $table) {
            $table->dropColumn([
                'crm_customer_id',
                'ssl_expires_at',
                'ssl_issuer',
                'ssl_auto_renew',
                'is_wildcard',
                'last_uptime_status',
                'last_uptime_checked_at',
            ]);
        });
    }
};
