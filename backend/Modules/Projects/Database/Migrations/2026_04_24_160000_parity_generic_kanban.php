<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prj_kanban_boards', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 191);
            $table->unsignedBigInteger('owner_id');
            $table->string('name', 191)->default('Kanban');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('prj_kanban_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('prj_kanban_boards')->cascadeOnDelete();
            $table->string('name', 191);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('color', 32)->nullable();
            $table->unsignedSmallInteger('wip_limit')->nullable();
            $table->timestamps();
        });

        Schema::create('prj_kanban_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('column_id')->constrained('prj_kanban_columns')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->nullableMorphs('cardable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_kanban_cards');
        Schema::dropIfExists('prj_kanban_columns');
        Schema::dropIfExists('prj_kanban_boards');
    }
};
