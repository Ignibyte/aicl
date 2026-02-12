<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rlm_failures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('failure_code')->unique()->index();
            $table->string('pattern_id')->nullable()->index();
            $table->string('category')->default('other');
            $table->string('subcategory')->nullable();
            $table->string('title');
            $table->text('description');
            $table->text('root_cause')->nullable();
            $table->text('fix')->nullable();
            $table->text('preventive_rule')->nullable();
            $table->string('severity')->default('medium');
            $table->json('entity_context')->nullable();
            $table->boolean('scaffolding_fixed')->default(false);
            $table->dateTime('first_seen_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->integer('report_count')->default(0);
            $table->integer('project_count')->default(0);
            $table->integer('resolution_count')->default(0);
            $table->decimal('resolution_rate', 5, 3)->nullable();
            $table->boolean('promoted_to_base')->default(false);
            $table->dateTime('promoted_at')->nullable();
            $table->string('aicl_version')->nullable();
            $table->string('laravel_version')->nullable();
            $table->string('status')->default('reported');
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('category');
            $table->index('severity');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rlm_failures');
    }
};
