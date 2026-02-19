<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rlm_lessons', function (Blueprint $table) {
            // Observation/Instruction/PreventionRule classification
            $table->string('lesson_type', 20)->default('observation')->after('source');
            $table->text('promotion_reason')->nullable()->after('lesson_type');
            $table->boolean('needs_review')->default(false)->after('is_active');

            $table->index('lesson_type');
            $table->index('needs_review');
        });
    }

    public function down(): void
    {
        Schema::table('rlm_lessons', function (Blueprint $table) {
            $table->dropIndex(['lesson_type']);
            $table->dropIndex(['needs_review']);

            $table->dropColumn([
                'lesson_type',
                'promotion_reason',
                'needs_review',
            ]);
        });
    }
};
