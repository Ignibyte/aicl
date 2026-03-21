<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type')->index();
            $table->morphs('notifiable');
            $table->nullableMorphs('sender');
            $table->json('channels');
            $table->json('channel_status');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('notification_channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 50)->index();
            $table->text('config');
            $table->jsonb('message_templates')->nullable();
            $table->jsonb('rate_limit')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
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
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('notification_logs');
    }
};
