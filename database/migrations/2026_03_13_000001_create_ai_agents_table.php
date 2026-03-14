<?php

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
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
