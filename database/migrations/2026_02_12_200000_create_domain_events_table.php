<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type');
            $table->string('actor_type', 50);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id'], 'idx_domain_events_entity');
            $table->index('event_type', 'idx_domain_events_event_type');
            $table->index('occurred_at', 'idx_domain_events_occurred_at');
            $table->index(['actor_type', 'actor_id'], 'idx_domain_events_actor');
            $table->index(['event_type', 'occurred_at'], 'idx_domain_events_event_type_occurred');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_events');
    }
};
