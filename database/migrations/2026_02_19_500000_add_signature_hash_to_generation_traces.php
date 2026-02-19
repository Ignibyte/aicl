<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_traces', function (Blueprint $table) {
            $table->string('signature_hash', 64)->nullable()->after('aicl_version');
        });
    }

    public function down(): void
    {
        Schema::table('generation_traces', function (Blueprint $table) {
            $table->dropColumn('signature_hash');
        });
    }
};
