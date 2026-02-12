<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prevention_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rlm_failure_id')->nullable()->constrained('rlm_failures')->nullOnDelete();
            $table->json('trigger_context')->nullable();
            $table->text('rule_text');
            $table->decimal('confidence', 5, 2)->default(0.0);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->integer('applied_count')->default(0);
            $table->dateTime('last_applied_at')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prevention_rules');
    }
};
