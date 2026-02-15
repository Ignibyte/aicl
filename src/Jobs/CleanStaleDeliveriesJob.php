<?php

namespace Aicl\Jobs;

use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CleanStaleDeliveriesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        $staleThreshold = now()->subHours(24);

        // Mark deliveries stuck in "pending" for more than 24 hours as failed
        $staleCount = NotificationDeliveryLog::query()
            ->where('status', DeliveryStatus::Pending)
            ->where('created_at', '<', $staleThreshold)
            ->update([
                'status' => DeliveryStatus::Failed,
                'error_message' => 'Marked as failed: stale delivery (pending > 24h)',
                'failed_at' => now(),
            ]);

        if ($staleCount > 0) {
            Log::info('Cleaned stale deliveries', ['count' => $staleCount]);
        }
    }
}
