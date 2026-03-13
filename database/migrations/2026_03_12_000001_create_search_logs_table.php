<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('query');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type_filter')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamp('searched_at');

            $table->index('searched_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
