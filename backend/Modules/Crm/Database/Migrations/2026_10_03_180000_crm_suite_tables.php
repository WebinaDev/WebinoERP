<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_activities', 'remind_at')) {
                $table->timestamp('remind_at')->nullable()->after('due_date');
            }
            if (! Schema::hasColumn('crm_activities', 'reminded_at')) {
                $table->timestamp('reminded_at')->nullable()->after('remind_at');
            }
            if (! Schema::hasColumn('crm_activities', 'meta')) {
                $table->json('meta')->nullable()->after('reminded_at');
            }
        });

        Schema::create('crm_score_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('kind', 20)->default('field');
            $table->string('target', 80);
            $table->string('operator', 20)->default('present');
            $table->string('match_value', 191)->nullable();
            $table->integer('weight')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->string('name', 160);
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crm_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->foreignId('template_id')->nullable()->constrained('crm_message_templates')->nullOnDelete();
            $table->string('related_type', 32);
            $table->unsignedBigInteger('related_id');
            $table->string('to_address', 191);
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('queued');
            $table->string('provider', 40)->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('crm_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('channel', 16)->default('email');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crm_sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('crm_sequences')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('crm_message_templates')->nullOnDelete();
            $table->unsignedInteger('delay_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('crm_sequences')->cascadeOnDelete();
            $table->string('related_type', 32);
            $table->unsignedBigInteger('related_id');
            $table->unsignedInteger('step_index')->default(0);
            $table->timestamp('next_run_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'next_run_at']);
        });

        Schema::create('crm_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->nullable()->constrained('crm_pipelines')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('crm_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 32);
            $table->string('key', 64);
            $table->string('label', 120);
            $table->string('type', 20)->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['entity', 'key']);
        });

        Schema::create('crm_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('crm_custom_fields')->cascadeOnDelete();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['field_id', 'entity_type', 'entity_id']);
        });

        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('color', 7)->default('#64748b');
            $table->timestamps();
        });

        Schema::create('crm_taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->string('taggable_type', 32);
            $table->unsignedBigInteger('taggable_id');
            $table->timestamps();
            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });

        Schema::create('crm_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('entity', 32)->default('account');
            $table->json('filters')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crm_marketing_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crm_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('crm_marketing_lists')->cascadeOnDelete();
            $table->string('member_type', 32);
            $table->unsignedBigInteger('member_id');
            $table->timestamps();
            $table->unique(['list_id', 'member_type', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_list_members');
        Schema::dropIfExists('crm_marketing_lists');
        Schema::dropIfExists('crm_segments');
        Schema::dropIfExists('crm_taggables');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('crm_custom_field_values');
        Schema::dropIfExists('crm_custom_fields');
        Schema::dropIfExists('crm_quotas');
        Schema::dropIfExists('crm_sequence_enrollments');
        Schema::dropIfExists('crm_sequence_steps');
        Schema::dropIfExists('crm_sequences');
        Schema::dropIfExists('crm_messages');
        Schema::dropIfExists('crm_message_templates');
        Schema::dropIfExists('crm_score_rules');

        Schema::table('crm_activities', function (Blueprint $table) {
            foreach (['meta', 'reminded_at', 'remind_at'] as $column) {
                if (Schema::hasColumn('crm_activities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
