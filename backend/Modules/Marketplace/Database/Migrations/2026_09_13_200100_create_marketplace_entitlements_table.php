<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255);
            $table->foreignId('module_id')->constrained('marketplace_modules')->cascadeOnDelete();
            $table->string('status', 20)->default('owned');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('installed_version', 50)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'module_id']);
            $table->index('status');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_entitlements');
    }
};
