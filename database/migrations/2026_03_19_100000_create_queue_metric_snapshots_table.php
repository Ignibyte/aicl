<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('queue_metric_snapshots', function (Blueprint $table) {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_metric_snapshots');
    }
};
