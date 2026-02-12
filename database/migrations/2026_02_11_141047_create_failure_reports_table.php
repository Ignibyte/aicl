<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failure_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rlm_failure_id')->constrained('rlm_failures')->cascadeOnDelete();
            $table->string('project_hash')->index();
            $table->string('entity_name');
            $table->json('scaffolder_args')->nullable();
            $table->string('phase')->nullable();
            $table->string('agent')->nullable();
            $table->boolean('resolved')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->string('resolution_method')->nullable();
            $table->integer('time_to_resolve')->nullable();
            $table->dateTime('reported_at');
            $table->dateTime('resolved_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('resolved');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failure_reports');
    }
};
