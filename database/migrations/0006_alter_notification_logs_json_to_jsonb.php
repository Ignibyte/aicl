<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert `notification_logs.channels` and `notification_logs.channel_status`
 * from `json` to `jsonb` on PostgreSQL.
 *
 * Laravel's Schema builder doesn't natively support json→jsonb column-type
 * changes on PostgreSQL, so we use a raw `ALTER TABLE ... ALTER COLUMN ...
 * TYPE jsonb USING ...::jsonb` statement. This is a full-table rewrite but
 * acceptable for notification_logs (small, periodically purged table).
 *
 * After this migration, `NotificationLog::scopeFailed`'s existing
 * `channel_status::jsonb` cast becomes a no-op but is kept for defensive
 * clarity against future column-type drift.
 *
 * Skips silently on non-PostgreSQL connections (e.g., SQLite in unit tests)
 * because those drivers treat `json` and `jsonb` interchangeably.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('notification_logs')) {
            return;
        }

        DB::statement('ALTER TABLE notification_logs ALTER COLUMN channels TYPE jsonb USING channels::jsonb');
        DB::statement('ALTER TABLE notification_logs ALTER COLUMN channel_status TYPE jsonb USING channel_status::jsonb');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('notification_logs')) {
            return;
        }

        DB::statement('ALTER TABLE notification_logs ALTER COLUMN channels TYPE json USING channels::json');
        DB::statement('ALTER TABLE notification_logs ALTER COLUMN channel_status TYPE json USING channel_status::json');
    }
};
