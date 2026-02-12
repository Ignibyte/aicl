<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rlm_semantic_cache', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cache_key')->unique();
            $table->string('check_name');
            $table->string('entity_name')->index();
            $table->boolean('passed');
            $table->text('message');
            $table->decimal('confidence', 3, 2)->default(1.00);
            $table->string('files_hash');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['cache_key', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rlm_semantic_cache');
    }
};
