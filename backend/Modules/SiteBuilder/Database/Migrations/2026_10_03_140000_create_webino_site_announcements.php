<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webino_site_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title_fa', 191);
            $table->string('title_en', 191)->nullable();
            $table->text('body_fa');
            $table->text('body_en')->nullable();
            $table->string('level', 16)->default('info');
            $table->string('status', 16)->default('draft');
            $table->json('audience');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('webino_site_announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('webino_site_announcements')->cascadeOnDelete();
            $table->foreignId('site_provision_id')->constrained('webino_site_provisions')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'site_provision_id'], 'site_announcement_delivery_unique');
            $table->index(['site_provision_id', 'status']);
        });

        Schema::table('webino_site_provisions', function (Blueprint $table) {
            $table->index('provision_token');
        });
    }

    public function down(): void
    {
        Schema::table('webino_site_provisions', function (Blueprint $table) {
            $table->dropIndex(['provision_token']);
        });
        Schema::dropIfExists('webino_site_announcement_deliveries');
        Schema::dropIfExists('webino_site_announcements');
    }
};
