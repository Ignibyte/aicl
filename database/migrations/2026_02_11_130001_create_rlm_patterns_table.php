<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rlm_patterns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description');
            $table->string('target')->index();
            $table->text('check_regex');
            $table->string('severity')->default('error');
            $table->decimal('weight', 5, 2)->default(1.0);
            $table->string('category')->index()->default('structural');
            $table->json('applies_when')->nullable();
            $table->string('source')->default('base');
            $table->boolean('is_active')->default(true);
            $table->integer('pass_count')->default(0);
            $table->integer('fail_count')->default(0);
            $table->dateTime('last_evaluated_at')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rlm_patterns');
    }
};
