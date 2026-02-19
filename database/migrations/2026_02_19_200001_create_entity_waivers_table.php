<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_waivers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_name', 100)->index();
            $table->string('pattern_id', 100)->index();
            $table->text('reason');
            $table->text('scope_justification');
            $table->string('ticket_url', 500)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_name', 'pattern_id'], 'entity_waivers_entity_pattern_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_waivers');
    }
};
