<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Knowledge & resources (blog / academy / magazine / downloads / FAQ / announcements): extra fields only, no data. */
return new class extends Migration
{
    private const COLUMNS = [
        'marketing_academy_courses' => [
            'level' => 'string', 'duration' => 'string', 'instructor' => 'string', 'outcomes' => 'text',
            'enroll_url' => 'string', 'is_free' => 'bool',
        ],
        'marketing_magazine_posts' => ['issue' => 'string', 'author' => 'string', 'featured' => 'bool'],
        'marketing_downloads' => ['slug' => 'string', 'description' => 'text', 'file_type' => 'string', 'file_url' => 'string', 'gated' => 'bool_true', 'related_url' => 'string'],
        'marketing_announcements' => ['slug' => 'string', 'type' => 'string', 'summary' => 'text'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $cols) {
            Schema::table($table, function (Blueprint $t) use ($table, $cols) {
                foreach ($cols as $name => $type) {
                    if (Schema::hasColumn($table, $name)) {
                        continue;
                    }
                    match ($type) {
                        'text' => $t->text($name)->nullable(),
                        'bool' => $t->boolean($name)->default(false),
                        'bool_true' => $t->boolean($name)->default(true),
                        default => $t->string($name, 500)->nullable(),
                    };
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $cols) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(array_keys($cols)));
        }
    }
};
