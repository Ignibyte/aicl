<?php

declare(strict_types=1);

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

        Schema::create('schedule_history', function (Blueprint $table): void {
            $table->id();
            $table->string('command');
            $table->string('description')->nullable();
            $table->string('expression', 100);
            $table->string('status', 20)->default('running');
            $table->integer('exit_code')->nullable();
            $table->text('output')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['command', 'started_at']);
            $table->index('status');
            $table->index('started_at');
        });

        Schema::create('queue_metric_snapshots', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('type', 10);
            $table->string('name', 255);
            $table->decimal('throughput', 10, 2);
            $table->decimal('runtime', 10, 2);
            $table->decimal('wait', 10, 2)->nullable();
            $table->timestamp('recorded_at');

            $table->index(['type', 'name', 'recorded_at'], 'queue_metrics_type_name_recorded_idx');
            $table->index('recorded_at', 'queue_metrics_recorded_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_metric_snapshots');
        Schema::dropIfExists('schedule_history');
        Schema::dropIfExists('search_logs');
    }
};
