<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_hosting_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('core_hosting_settings', 'platform_base_domain')) {
                $table->string('platform_base_domain', 255)->nullable()->after('git_webhook_secret');
            }
            if (! Schema::hasColumn('core_hosting_settings', 'default_product_channel')) {
                $table->string('default_product_channel', 16)->default('LTS')->after('platform_base_domain');
            }
            if (! Schema::hasColumn('core_hosting_settings', 'provision_webhook_secret')) {
                $table->text('provision_webhook_secret')->nullable()->after('default_product_channel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('core_hosting_settings', function (Blueprint $table) {
            $cols = [];
            foreach (['platform_base_domain', 'default_product_channel', 'provision_webhook_secret'] as $col) {
                if (Schema::hasColumn('core_hosting_settings', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
