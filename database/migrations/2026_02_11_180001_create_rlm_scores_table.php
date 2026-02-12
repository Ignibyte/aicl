<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rlm_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_name')->index();
            $table->string('score_type')->index();
            $table->integer('passed');
            $table->integer('total');
            $table->decimal('percentage', 5, 2);
            $table->integer('errors')->default(0);
            $table->integer('warnings')->default(0);
            $table->text('details')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_name', 'score_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rlm_scores');
    }
};
