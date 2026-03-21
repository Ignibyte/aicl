<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->text('system_prompt')->nullable();
            $table->integer('max_tokens')->default(4096);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->integer('context_window')->default(128000);
            $table->integer('context_messages')->default(20);
            $table->boolean('is_active')->default(false);
            $table->string('icon', 100)->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->jsonb('suggested_prompts')->nullable();
            $table->jsonb('capabilities')->nullable();
            $table->jsonb('visible_to_roles')->nullable();
            $table->integer('max_requests_per_minute')->nullable();
            $table->string('state')->default('draft');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('provider');
            $table->index(['is_active', 'sort_order']);
            $table->index('state');
        });

        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 255)->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ai_agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->integer('message_count')->default(0);
            $table->integer('token_count')->default(0);
            $table->text('summary')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->string('context_page', 500)->nullable();
            $table->timestampTz('last_message_at')->nullable();
            $table->string('state')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'last_message_at']);
            $table->index('ai_agent_id');
            $table->index('state');
            $table->index(['user_id', 'is_pinned']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->integer('token_count')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['ai_conversation_id', 'created_at']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_agents');
    }
};
