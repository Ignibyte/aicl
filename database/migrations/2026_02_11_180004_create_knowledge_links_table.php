<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('source');
            $table->uuidMorphs('target');
            $table->string('relationship')->index();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'target_type', 'target_id', 'relationship'], 'knowledge_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_links');
    }
};
