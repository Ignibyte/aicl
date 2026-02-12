<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rlm_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('topic')->index();
            $table->string('subtopic')->nullable();
            $table->string('summary');
            $table->text('detail');
            $table->string('tags')->nullable();
            $table->json('context_tags')->nullable();
            $table->string('source')->nullable();
            $table->decimal('confidence', 5, 2)->default(1.0);
            $table->boolean('is_verified')->default(false);
            $table->integer('view_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_verified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rlm_lessons');
    }
};
