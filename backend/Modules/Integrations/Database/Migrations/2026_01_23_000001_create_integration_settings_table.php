<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('integration', 50)->index();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['integration', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
