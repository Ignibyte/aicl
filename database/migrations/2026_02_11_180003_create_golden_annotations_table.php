<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('golden_annotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('annotation_key')->unique();
            $table->string('file_path');
            $table->integer('line_number')->nullable();
            $table->text('annotation_text');
            $table->text('rationale')->nullable();
            $table->jsonb('feature_tags')->default('[]');
            $table->string('pattern_name')->nullable()->index();
            $table->string('category')->index();
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('file_path');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('golden_annotations');
    }
};
