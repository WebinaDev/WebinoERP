<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modirpayamak_domain_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255);
            $table->string('number', 50);
            $table->string('role', 20)->default('service');
            $table->string('label', 255)->nullable();
            $table->boolean('is_default')->default(true);
            $table->timestamps();
            $table->unique(['domain', 'number'], 'domain_number');
            $table->index('domain');
            $table->index('number');
            $table->index(['domain', 'role'], 'domain_role');
        });

        Schema::create('modirpayamak_pattern_registry', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255);
            $table->string('scope', 30);
            $table->string('event_key', 80);
            $table->string('ippanel_code', 100)->nullable();
            $table->json('param_map')->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['domain', 'scope', 'event_key'], 'domain_scope_event');
            $table->index('sync_status');
            $table->index('ippanel_code');
        });

        Schema::create('modirpayamak_messages', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->nullable();
            $table->string('sending_type', 40)->default('webservice');
            $table->string('from_number', 50)->nullable();
            $table->string('pattern_code', 100)->nullable();
            $table->text('message')->nullable();
            $table->json('recipients')->nullable();
            $table->string('status', 40)->default('sent');
            $table->decimal('cost', 15, 2)->default(0);
            $table->json('edge_payload')->nullable();
            $table->timestamps();
            $table->index(['domain', 'created_at']);
            $table->index('status');
        });

        if (Schema::hasTable('modirpayamak_orders') && ! Schema::hasColumn('modirpayamak_orders', 'credit_amount')) {
            Schema::table('modirpayamak_orders', function (Blueprint $table) {
                $table->decimal('credit_amount', 15, 2)->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modirpayamak_orders') && Schema::hasColumn('modirpayamak_orders', 'credit_amount')) {
            Schema::table('modirpayamak_orders', function (Blueprint $table) {
                $table->dropColumn('credit_amount');
            });
        }
        Schema::dropIfExists('modirpayamak_messages');
        Schema::dropIfExists('modirpayamak_pattern_registry');
        Schema::dropIfExists('modirpayamak_domain_numbers');
    }
};
