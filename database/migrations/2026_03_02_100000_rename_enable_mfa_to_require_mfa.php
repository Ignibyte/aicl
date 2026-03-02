<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename the setting for existing installs that have the old key.
        // Fresh installs will use the updated feature_settings.php which
        // already creates 'require_mfa' directly.
        DB::table('settings')
            ->where('group', 'features')
            ->where('name', 'enable_mfa')
            ->update([
                'name' => 'require_mfa',
                // Reset to false — the old toggle was never wired, so any
                // existing value is meaningless.
                'payload' => json_encode(false),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'features')
            ->where('name', 'require_mfa')
            ->update([
                'name' => 'enable_mfa',
                'updated_at' => now(),
            ]);
    }
};
