<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distilled_lessons', function (Blueprint $table) {
            $table->decimal('base_impact_score', 8, 2)->nullable()->after('impact_score');
        });
    }

    public function down(): void
    {
        Schema::table('distilled_lessons', function (Blueprint $table) {
            $table->dropColumn('base_impact_score');
        });
    }
};
