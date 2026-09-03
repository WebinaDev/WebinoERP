<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ssh_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('fingerprint', 128)->nullable();
            $table->text('public_key')->nullable();
            $table->text('private_key'); // encrypted via model cast
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('platform_servers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('ip', 64);
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('user', 64)->default('root');
            $table->foreignId('ssh_key_id')->nullable()->constrained('platform_ssh_keys')->nullOnDelete();
            $table->string('status', 32)->default('pending'); // pending|validating|reachable|unreachable|setup
            $table->boolean('is_localhost')->default(false);
            $table->string('proxy_type', 32)->default('caddy');
            $table->json('meta')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_destinations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('server_id')->constrained('platform_servers')->cascadeOnDelete();
            $table->string('name');
            $table->string('network_name');
            $table->string('driver', 32)->default('bridge');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('crm_account_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_environments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('platform_projects')->cascadeOnDelete();
            $table->string('name'); // production|staging|custom
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::create('platform_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('environment_id')->constrained('platform_environments')->cascadeOnDelete();
            $table->foreignId('server_id')->constrained('platform_servers')->cascadeOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained('platform_destinations')->nullOnDelete();
            $table->string('type', 32); // application|database|service|webino_dashboard
            $table->string('name');
            $table->string('status', 32)->default('draft');
            $table->string('fqdn')->nullable();
            $table->string('build_pack', 32)->nullable(); // nixpacks|dockerfile|compose|image
            $table->string('git_repository')->nullable();
            $table->string('git_branch')->nullable();
            $table->string('dockerfile_location')->nullable();
            $table->string('docker_compose_location')->nullable();
            $table->longText('docker_compose_raw')->nullable();
            $table->string('docker_image')->nullable();
            $table->string('database_type', 32)->nullable();
            $table->string('service_template')->nullable();
            $table->string('site_type_slug', 32)->nullable();
            $table->foreignId('license_id')->nullable();
            $table->foreignId('crm_account_id')->nullable();
            $table->foreignId('provision_id')->nullable();
            $table->unsignedInteger('ports_exposes')->nullable();
            $table->json('settings')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_env_vars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('platform_resources')->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->boolean('is_buildtime')->default(false);
            $table->boolean('is_runtime')->default(true);
            $table->boolean('is_preview')->default(false);
            $table->timestamps();
            $table->unique(['resource_id', 'key']);
        });

        Schema::create('platform_volumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('platform_resources')->cascadeOnDelete();
            $table->string('name');
            $table->string('mount_path');
            $table->string('host_path')->nullable();
            $table->boolean('is_file')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('platform_resources')->cascadeOnDelete();
            $table->string('domain');
            $table->boolean('force_https')->default(true);
            $table->boolean('hsts')->default(false);
            $table->string('ssl_status', 32)->default('pending');
            $table->string('redirect_to')->nullable();
            $table->timestamps();
            $table->unique(['resource_id', 'domain']);
        });

        Schema::create('platform_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('resource_id')->constrained('platform_resources')->cascadeOnDelete();
            $table->string('status', 32)->default('queued');
            $table->string('commit')->nullable();
            $table->longText('logs')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_storages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('driver', 32)->default('s3');
            $table->string('endpoint')->nullable();
            $table->string('bucket')->nullable();
            $table->string('region')->nullable();
            $table->text('access_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->boolean('path_style')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('platform_resources')->cascadeOnDelete();
            $table->foreignId('storage_id')->nullable()->constrained('platform_storages')->nullOnDelete();
            $table->string('cron')->default('0 2 * * *');
            $table->unsignedInteger('retention_days')->default(14);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_backups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('resource_id')->constrained('platform_resources')->cascadeOnDelete();
            $table->foreignId('storage_id')->nullable()->constrained('platform_storages')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_sources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('provider', 32); // github|gitlab|gitea|bitbucket
            $table->string('base_url')->nullable();
            $table->text('token')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32); // email|discord|telegram|slack
            $table->json('config');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_shared_variables', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->foreignId('project_id')->nullable()->constrained('platform_projects')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('platform_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('platform_taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('platform_tags')->cascadeOnDelete();
            $table->morphs('taggable');
            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });

        Schema::create('platform_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token_hash', 128)->unique();
            $table->json('abilities'); // read|read:sensitive|write|deploy
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_service_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category', 64);
            $table->text('description')->nullable();
            $table->longText('compose');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = [
            'platform_service_templates', 'platform_settings', 'platform_api_tokens',
            'platform_taggables', 'platform_tags', 'platform_shared_variables',
            'platform_notification_channels', 'platform_sources', 'platform_backups',
            'platform_backup_schedules', 'platform_storages', 'platform_deployments',
            'platform_domains', 'platform_volumes', 'platform_env_vars',
            'platform_resources', 'platform_environments', 'platform_projects',
            'platform_destinations', 'platform_servers', 'platform_ssh_keys',
        ];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
    }
};
