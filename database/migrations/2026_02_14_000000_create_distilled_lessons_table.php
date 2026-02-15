<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distilled_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identity
            $table->string('lesson_code', 20)->unique();
            $table->string('title');
            $table->text('guidance');

            // Targeting
            $table->string('target_agent');
            $table->unsignedTinyInteger('target_phase');
            $table->jsonb('trigger_context')->nullable();

            // Source tracking
            $table->jsonb('source_failure_codes');
            $table->jsonb('source_lesson_ids')->nullable();

            // Ranking
            $table->float('impact_score');
            $table->float('confidence');
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('prevented_count')->default(0);
            $table->unsignedInteger('ignored_count')->default(0);

            // Lifecycle
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_distilled_at');
            $table->unsignedInteger('generation')->default(1);

            // Standard
            $table->foreignId('owner_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distilled_lessons');
    }
};
