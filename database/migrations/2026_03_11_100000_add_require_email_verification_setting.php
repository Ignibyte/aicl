<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add the require_email_verification setting if it doesn't exist
        $exists = DB::table('settings')
            ->where('group', 'features')
            ->where('name', 'require_email_verification')
            ->exists();

        if (! $exists) {
            DB::table('settings')->insert([
                'group' => 'features',
                'name' => 'require_email_verification',
                'locked' => false,
                'payload' => json_encode(true),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'features')
            ->where('name', 'require_email_verification')
            ->delete();
    }
};
