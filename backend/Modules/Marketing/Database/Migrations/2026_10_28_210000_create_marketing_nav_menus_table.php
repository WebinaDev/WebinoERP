<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** «منوها» — WordPress-style nav menus (nested items json + assigned locations), same shape as WebinoDashboard nav_menus. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_nav_menus')) {
            return;
        }
        Schema::create('marketing_nav_menus', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('slug', 120)->unique();
            $t->json('items')->nullable();
            $t->json('locations')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_nav_menus');
    }
};
