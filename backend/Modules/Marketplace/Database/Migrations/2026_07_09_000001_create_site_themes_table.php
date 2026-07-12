<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_fa');
            $table->string('name_en');
            $table->string('preview_url')->nullable();
            $table->string('package_path')->nullable();
            $table->json('business_types')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_themes');
    }
};
