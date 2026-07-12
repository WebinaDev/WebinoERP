<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bale_automation_queue', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 64)->index();
            $table->string('trigger_key', 64);
            $table->string('step_key', 64);
            $table->timestamp('scheduled_for')->index();
            $table->string('status', 20)->default('queued')->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bale_automation_queue');
    }
};
