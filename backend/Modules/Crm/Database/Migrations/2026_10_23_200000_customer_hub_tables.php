<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer 360 Hub — extends the existing central customer entity (crm_accounts)
 * and adds only the satellite tables that did not exist anywhere in the ERP.
 * Reused as-is: crm_contacts, crm_activities (notes / calls / meetings), crm_messages,
 * crm_deals, prj_* (projects, tasks, tickets, contracts, time entries), sales_invoices,
 * payment_intents / payment_wallets, core_licenses, webino_site_provisions, platform_resources.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_accounts', function (Blueprint $table) {
            $add = function (string $col, callable $def) use ($table) {
                if (! Schema::hasColumn('crm_accounts', $col)) {
                    $def($table);
                }
            };
            $add('customer_kind', fn ($t) => $t->string('customer_kind', 20)->nullable());   // individual|legal|partner|vip
            $add('hub_status', fn ($t) => $t->string('hub_status', 20)->default('active'));    // active|dormant|debtor|blocked|prospect
            $add('brand_name', fn ($t) => $t->string('brand_name', 200)->nullable());
            $add('national_id', fn ($t) => $t->string('national_id', 30)->nullable());
            $add('economic_code', fn ($t) => $t->string('economic_code', 30)->nullable());
            $add('registration_no', fn ($t) => $t->string('registration_no', 30)->nullable());
            $add('postal_code', fn ($t) => $t->string('postal_code', 20)->nullable());
            $add('address_line', fn ($t) => $t->text('address_line')->nullable());
            $add('city', fn ($t) => $t->string('city', 100)->nullable());
            $add('province', fn ($t) => $t->string('province', 100)->nullable());
            $add('country', fn ($t) => $t->string('country', 100)->nullable());
            $add('logo_url', fn ($t) => $t->string('logo_url', 1000)->nullable());
            $add('kyc_status', fn ($t) => $t->string('kyc_status', 20)->default('none')); // none|pending|verified|rejected
            $add('kyc_note', fn ($t) => $t->text('kyc_note')->nullable());
            $add('kyc_verified_at', fn ($t) => $t->timestamp('kyc_verified_at')->nullable());
            $add('preferred_language', fn ($t) => $t->string('preferred_language', 10)->nullable());
            $add('preferred_channel', fn ($t) => $t->string('preferred_channel', 20)->nullable()); // phone|sms|email|whatsapp
            $add('timezone', fn ($t) => $t->string('timezone', 64)->nullable());
            $add('marketing_sms_opt_out', fn ($t) => $t->boolean('marketing_sms_opt_out')->default(false));
            $add('referrer_account_id', fn ($t) => $t->unsignedBigInteger('referrer_account_id')->nullable());
            $add('partner_account_id', fn ($t) => $t->unsignedBigInteger('partner_account_id')->nullable());
            $add('credit_limit', fn ($t) => $t->decimal('credit_limit', 15, 2)->nullable());
            $add('allowed_ips', fn ($t) => $t->json('allowed_ips')->nullable());
            $add('suspended_at', fn ($t) => $t->timestamp('suspended_at')->nullable());
        });

        Schema::table('crm_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_contacts', 'whatsapp')) {
                $table->string('whatsapp', 20)->nullable();
            }
            if (! Schema::hasColumn('crm_contacts', 'contact_role')) {
                $table->string('contact_role', 20)->nullable(); // manager|finance|technical|support|other
            }
        });

        Schema::table('core_licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('core_licenses', 'crm_account_id')) {
                $table->unsignedBigInteger('crm_account_id')->nullable()->index();
            }
            if (! Schema::hasColumn('core_licenses', 'license_type')) {
                $table->string('license_type', 20)->nullable(); // monthly|yearly|perpetual|trial
            }
            if (! Schema::hasColumn('core_licenses', 'usage_limits')) {
                $table->json('usage_limits')->nullable();
            }
            if (! Schema::hasColumn('core_licenses', 'usage_current')) {
                $table->json('usage_current')->nullable();
            }
            if (! Schema::hasColumn('core_licenses', 'feature_flags')) {
                $table->json('feature_flags')->nullable();
            }
        });

        if (! Schema::hasTable('crm_customer_documents')) {
            Schema::create('crm_customer_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->string('category', 30); // national_card|gazette|enamad|permit|contract|proposal|invoice|brief|deliverable|other
                $table->string('title', 255);
                $table->string('url', 1000)->nullable();
                $table->unsignedBigInteger('core_document_id')->nullable();
                $table->date('expires_at')->nullable();
                $table->string('review_status', 20)->default('pending'); // pending|approved|rejected
                $table->text('review_note')->nullable();
                $table->json('visible_to_roles')->nullable(); // null = everyone with hub documents access
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_customer_domains')) {
            Schema::create('crm_customer_domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->string('domain', 255);
                $table->string('registrar', 100)->nullable();
                $table->date('registered_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->json('nameservers')->nullable();
                $table->unsignedBigInteger('site_provision_id')->nullable();
                $table->boolean('auto_renew')->default(false);
                $table->timestamps();
                $table->unique(['account_id', 'domain']);
            });
        }

        if (! Schema::hasTable('crm_customer_services')) {
            // Hosting / VPS / infra services and third-party integrations (gateway, SMS, accounting, shipping, API keys).
            Schema::create('crm_customer_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->string('kind', 30); // hosting|vps|gateway|sms|accounting|shipping|api_key|other
                $table->string('name', 200);
                $table->string('provider', 100)->nullable();
                $table->string('plan', 100)->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('status', 20)->default('active');
                $table->string('site_domain', 255)->nullable();
                $table->string('key_last4', 8)->nullable(); // never the full secret
                $table->date('renews_at')->nullable();
                $table->timestamp('last_backup_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_customer_relations')) {
            Schema::create('crm_customer_relations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->foreignId('related_account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->string('relation_type', 20); // holding|related|partner|referral
                $table->string('note', 255)->nullable();
                $table->timestamps();
                $table->unique(['account_id', 'related_account_id', 'relation_type'], 'crm_cust_rel_unique');
            });
        }

        if (! Schema::hasTable('crm_customer_alerts')) {
            // Pinned internal notes + manual risk alerts (automatic alerts are computed, not stored).
            Schema::create('crm_customer_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->string('kind', 20)->default('pinned_note'); // pinned_note|risk
                $table->string('severity', 10)->default('info'); // info|warning|critical
                $table->text('body');
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_customer_onboarding_items')) {
            Schema::create('crm_customer_onboarding_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->string('title', 255);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamp('done_at')->nullable();
                $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_customer_usage_metrics')) {
            Schema::create('crm_customer_usage_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->unsignedBigInteger('site_provision_id')->nullable();
                $table->string('metric', 40); // sms_sent|orders|traffic_mb|storage_mb|bandwidth_mb|panel_users|cpu_pct|ram_mb
                $table->decimal('value', 18, 2)->default(0);
                $table->date('period');
                $table->timestamps();
                $table->index(['account_id', 'metric', 'period']);
            });
        }

        if (! Schema::hasTable('crm_customer_audit')) {
            Schema::create('crm_customer_audit', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 60);
                $table->json('changes')->nullable();
                $table->string('ip', 64)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['crm_customer_audit', 'crm_customer_usage_metrics', 'crm_customer_onboarding_items', 'crm_customer_alerts',
            'crm_customer_relations', 'crm_customer_services', 'crm_customer_domains', 'crm_customer_documents'] as $t) {
            Schema::dropIfExists($t);
        }
        $drop = function (string $table, array $cols) {
            $present = array_values(array_filter($cols, fn ($c) => Schema::hasColumn($table, $c)));
            if ($present) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($present));
            }
        };
        $drop('core_licenses', ['crm_account_id', 'license_type', 'usage_limits', 'usage_current', 'feature_flags']);
        $drop('crm_contacts', ['whatsapp', 'contact_role']);
        $drop('crm_accounts', ['customer_kind', 'hub_status', 'brand_name', 'national_id', 'economic_code', 'registration_no',
            'postal_code', 'address_line', 'city', 'province', 'country', 'logo_url', 'kyc_status', 'kyc_note', 'kyc_verified_at',
            'preferred_language', 'preferred_channel', 'timezone', 'marketing_sms_opt_out', 'referrer_account_id',
            'partner_account_id', 'credit_limit', 'allowed_ips', 'suspended_at']);
    }
};
