<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_traces', function (Blueprint $table) {
            $table->string('scaffolder_version', 50)->nullable()->after('laravel_version');
            $table->string('pattern_set_version', 100)->nullable()->after('scaffolder_version');
        });
    }

    public function down(): void
    {
        Schema::table('generation_traces', function (Blueprint $table) {
            $table->dropColumn([
                'scaffolder_version',
                'pattern_set_version',
            ]);
        });
    }
};
