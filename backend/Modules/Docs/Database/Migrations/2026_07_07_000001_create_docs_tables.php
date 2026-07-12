<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docs_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('party_name')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('body')->nullable();
            $table->date('signed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('docs_files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_files');
        Schema::dropIfExists('docs_contracts');
    }
};
