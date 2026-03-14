<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
