<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_basalam_settings', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 100)->nullable();
            $table->text('client_secret')->nullable();
            $table->string('redirect_uri', 500)->nullable();
            $table->text('scopes')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_basalam_connections', function (Blueprint $table) {
            $table->id();
            $table->string('site_url', 500)->unique();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('status', 32)->default('disconnected');
            $table->text('tokens')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_basalam_connections');
        Schema::dropIfExists('marketplace_basalam_settings');
    }
};
