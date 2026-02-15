<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action');
            $table->string('from_status');
            $table->string('to_status');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
