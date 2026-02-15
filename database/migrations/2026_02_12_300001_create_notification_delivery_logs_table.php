<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_log_id');
            $table->uuid('channel_id');
            $table->string('status', 50)->default('pending')->index();
            $table->integer('attempt_count')->default(0);
            $table->jsonb('payload')->nullable();
            $table->jsonb('response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('notification_log_id')
                ->references('id')
                ->on('notification_logs')
                ->cascadeOnDelete();

            $table->foreign('channel_id')
                ->references('id')
                ->on('notification_channels')
                ->cascadeOnDelete();

            $table->index('notification_log_id');
            $table->index('channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
    }
};
