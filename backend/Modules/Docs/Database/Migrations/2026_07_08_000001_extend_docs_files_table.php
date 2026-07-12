<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docs_files', function (Blueprint $table) {
            if (! Schema::hasColumn('docs_files', 'folder_id')) {
                $table->unsignedBigInteger('folder_id')->nullable()->after('name');
            }
            if (! Schema::hasColumn('docs_files', 'disk')) {
                $table->string('disk', 50)->default('local')->after('path');
            }
            if (! Schema::hasColumn('docs_files', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('size');
            }
            if (! Schema::hasColumn('docs_files', 'share_token')) {
                $table->string('share_token', 64)->nullable()->after('version');
            }
        });

        Schema::table('docs_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('docs_contracts', 'meta')) {
                $table->json('meta')->nullable()->after('body');
            }
        });
    }

    public function down(): void
    {
        Schema::table('docs_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('docs_contracts', 'meta')) {
                $table->dropColumn('meta');
            }
        });

        Schema::table('docs_files', function (Blueprint $table) {
            $cols = array_filter(['folder_id', 'disk', 'version', 'share_token'], fn ($c) => Schema::hasColumn('docs_files', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
