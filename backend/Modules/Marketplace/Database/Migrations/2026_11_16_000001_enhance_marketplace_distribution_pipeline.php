<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ارتقای جدول Releaseها با فیلدهای کنترل کیفیت و یکپارچگی پایپ‌لاین
        Schema::table('marketplace_releases', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_releases', 'checksum_sha256')) {
                $table->string('checksum_sha256', 64)->nullable()->after('package_source');
            }
            if (! Schema::hasColumn('marketplace_releases', 'package_size_bytes')) {
                $table->bigInteger('package_size_bytes')->nullable()->after('checksum_sha256');
            }
            if (! Schema::hasColumn('marketplace_releases', 'release_channel')) {
                $table->string('release_channel', 30)->default('stable')->after('package_size_bytes'); // stable, beta, nightly
            }
            if (! Schema::hasColumn('marketplace_releases', 'min_core_version')) {
                $table->string('min_core_version', 50)->nullable()->after('release_channel');
            }
            if (! Schema::hasColumn('marketplace_releases', 'max_core_version')) {
                $table->string('max_core_version', 50)->nullable()->after('min_core_version');
            }
            if (! Schema::hasColumn('marketplace_releases', 'has_client_dist')) {
                $table->boolean('has_client_dist')->default(false)->after('max_core_version');
            }
            if (! Schema::hasColumn('marketplace_releases', 'signature')) {
                $table->text('signature')->nullable()->after('has_client_dist');
            }
            if (! Schema::hasColumn('marketplace_releases', 'published_by_user_id')) {
                $table->unsignedBigInteger('published_by_user_id')->nullable()->index()->after('signature');
            }
        });

        // 2. جدول ثبت رویدادها و لاگ حسابرسی انتشار پکیج‌ها (Marketplace Distribution Audit)
        if (! Schema::hasTable('marketplace_distribution_audits')) {
            Schema::create('marketplace_distribution_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('release_id')->constrained('marketplace_releases')->cascadeOnDelete();
                $table->foreignId('module_id')->constrained('marketplace_modules')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action', 50); // build, validate, publish, download, rollback
                $table->string('checksum_sha256', 64)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('validation_details')->nullable();
                $table->timestamps();

                $table->index(['module_id', 'action', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_distribution_audits');

        Schema::table('marketplace_releases', function (Blueprint $table) {
            $table->dropColumn([
                'checksum_sha256',
                'package_size_bytes',
                'release_channel',
                'min_core_version',
                'max_core_version',
                'has_client_dist',
                'signature',
                'published_by_user_id',
            ]);
        });
    }
};
