<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_links', function (Blueprint $table) {
            // Standardized proof link type for recall ranking
            $table->string('link_type', 30)->nullable()->after('relationship');
            // Reference path/identifier (file path, test class::method, commit SHA, doc URL)
            $table->string('reference', 500)->nullable()->after('link_type');

            $table->index('link_type');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_links', function (Blueprint $table) {
            $table->dropIndex(['link_type']);

            $table->dropColumn([
                'link_type',
                'reference',
            ]);
        });
    }
};
