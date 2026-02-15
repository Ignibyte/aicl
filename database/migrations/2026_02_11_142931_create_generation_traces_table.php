<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_traces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_name')->index();
            $table->string('project_hash')->nullable()->index();
            $table->text('scaffolder_args');
            $table->json('file_manifest')->nullable();
            $table->decimal('structural_score', 5, 2)->nullable();
            $table->decimal('semantic_score', 5, 2)->nullable();
            $table->text('test_results')->nullable();
            $table->json('fixes_applied')->nullable();
            $table->integer('fix_iterations')->default(0);
            $table->integer('pipeline_duration')->nullable();
            $table->json('agent_versions')->nullable();
            $table->boolean('is_processed')->default(false)->index();
            $table->string('aicl_version')->nullable();
            $table->string('laravel_version')->nullable();
            $table->unsignedInteger('known_failure_count')->default(0);
            $table->unsignedInteger('novel_failure_count')->default(0);
            $table->jsonb('surfaced_lesson_codes')->nullable();
            $table->jsonb('failure_codes_hit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_traces');
    }
};
