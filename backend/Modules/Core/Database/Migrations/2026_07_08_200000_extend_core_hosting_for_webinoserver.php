<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_hosting_settings', function (Blueprint $table) {
            $table->string('webinoserver_panel_url', 512)->nullable()->after('git_webhook_secret');
            $table->text('webinoserver_api_token')->nullable()->after('webinoserver_panel_url');
            $table->string('platform_base_domain', 255)->nullable()->after('webinoserver_api_token');
            $table->string('default_product_channel', 16)->default('LTS')->after('platform_base_domain');
            $table->text('provision_webhook_secret')->nullable()->after('default_product_channel');
        });
    }

    public function down(): void
    {
        Schema::table('core_hosting_settings', function (Blueprint $table) {
            $table->dropColumn([
                'webinoserver_panel_url',
                'webinoserver_api_token',
                'platform_base_domain',
                'default_product_channel',
                'provision_webhook_secret',
            ]);
        });
    }
};
