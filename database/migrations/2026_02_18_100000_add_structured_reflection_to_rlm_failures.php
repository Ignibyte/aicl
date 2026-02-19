<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rlm_failures', function (Blueprint $table) {
            // Structured reflection fields (4-part schema)
            // Note: `fix` and `preventive_rule` already exist on the table and serve as
            // the structured "fix" and "rule" fields respectively. We add `attempt` and
            // `feedback` to complete the 4-part reflection schema.
            $table->text('attempt')->nullable()->after('description');
            $table->text('feedback')->nullable()->after('attempt');

            // Rule normalization for deterministic clustering
            // Computed from `preventive_rule` via RuleNormalizer
            $table->string('rule_norm', 500)->nullable()->after('preventive_rule');
            $table->string('rule_hash', 40)->nullable()->index()->after('rule_norm');

            // Identity metadata for analytics
            $table->string('validator_layer', 10)->nullable()->after('rule_hash');
            $table->string('validator_id', 50)->nullable()->index()->after('validator_layer');
            $table->string('entity_name', 100)->nullable()->index()->after('validator_id');
            $table->string('phase', 20)->nullable()->after('entity_name');
            $table->string('file_path', 500)->nullable()->after('phase');
            $table->foreignUuid('trace_id')->nullable()->constrained('generation_traces')->nullOnDelete()->after('file_path');

            // Composite index for "top failing patterns by phase" analytics
            $table->index(['validator_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::table('rlm_failures', function (Blueprint $table) {
            $table->dropIndex(['validator_id', 'phase']);
            $table->dropForeign(['trace_id']);
            $table->dropIndex(['rule_hash']);
            $table->dropIndex(['validator_id']);
            $table->dropIndex(['entity_name']);

            $table->dropColumn([
                'attempt',
                'feedback',
                'rule_norm',
                'rule_hash',
                'validator_layer',
                'validator_id',
                'entity_name',
                'phase',
                'file_path',
                'trace_id',
            ]);
        });
    }
};
