<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-item SEO overrides (title, description, og_image, canonical, noindex) for every public content type. */
return new class extends Migration
{
    private array $tables = ['marketing_blog_posts', 'marketing_magazine_posts', 'marketing_academy_courses', 'marketing_academy_lessons', 'marketing_announcements', 'marketing_downloads', 'marketing_portfolio_items', 'marketing_services', 'marketing_solution_pages', 'marketing_solution_industries'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (Schema::hasTable($t) && ! Schema::hasColumn($t, 'seo')) {
                Schema::table($t, fn (Blueprint $table) => $table->json('seo')->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (Schema::hasColumn($t, 'seo')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn('seo'));
            }
        }
    }
};
