<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_document_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->foreignId('parent_id')->nullable()->constrained('core_document_folders')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('core_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('core_document_folders')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 50)->default('public');
            $table->string('path', 500);
            $table->string('name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamp('last_uploaded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('core_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('core_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('disk', 50)->default('public');
            $table->string('path', 500);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('core_document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('core_documents')->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('core_document_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('core_documents')->cascadeOnDelete();
            $table->string('tag', 64)->index();
            $table->timestamps();
            $table->unique(['document_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_document_tags');
        Schema::dropIfExists('core_document_shares');
        Schema::dropIfExists('core_document_versions');
        Schema::dropIfExists('core_documents');
        Schema::dropIfExists('core_document_folders');
    }
};
