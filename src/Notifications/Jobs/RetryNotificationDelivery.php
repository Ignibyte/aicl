<?php

declare(strict_types=1);

namespace Aicl\Notifications\Jobs;

use Aicl\Notifications\DriverRegistry;
use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued job that retries a failed notification delivery via the appropriate channel driver.
 *
 * @codeCoverageIgnore Notification job processing
 */
class RetryNotificationDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $deliveryLogId,
    ) {
        $this->onQueue(config('aicl.notifications.queue', 'notifications'));
    }

    public function handle(DriverRegistry $registry): void
    {
        try {
            $log = NotificationDeliveryLog::find($this->deliveryLogId);
        } catch (QueryException) {
            return;
        }

        if (! $log) {
            return;
        }

        $maxAttempts = (int) config('aicl.notifications.retry.max_attempts', 5);

        if ($log->attempt_count >= $maxAttempts) {
            $log->update([
                'status' => DeliveryStatus::Failed,
                'failed_at' => now(),
            ]);

            return;
        }

        $channel = $log->channel;

        if (! $channel || ! $channel->is_active) {
            $log->update([
                'status' => DeliveryStatus::Failed,
                'failed_at' => now(),
                'error_message' => 'Channel not found or inactive.',
            ]);

            return;
        }

        $driver = $registry->resolve($channel->type);
        $result = $driver->send($channel, $log->payload ?? []);

        if ($result->success) {
            $log->update([
                'status' => DeliveryStatus::Delivered,
                'delivered_at' => now(),
                'sent_at' => $log->sent_at ?? now(),
                'response' => $result->response,
                'attempt_count' => $log->attempt_count + 1,
            ]);

            return;
        }

        $attempt = $log->attempt_count + 1;

        if ($result->retryable && $attempt < $maxAttempts) {
            $delay = $this->calculateDelay($attempt);
            $log->update([
                'status' => DeliveryStatus::Failed,
                'attempt_count' => $attempt,
                'error_message' => $result->error,
                'response' => $result->response,
                'next_retry_at' => now()->addSeconds($delay),
            ]);
            static::dispatch($this->deliveryLogId)->delay($delay);
        } else {
            $log->update([
                'status' => DeliveryStatus::Failed,
                'failed_at' => now(),
                'attempt_count' => $attempt,
                'error_message' => $result->error,
                'response' => $result->response,
            ]);
        }
    }

    /**
     * Exponential backoff with jitter: base * 2^(attempt-1) + random(0, base).
     */
    protected function calculateDelay(int $attempt): int
    {
        $base = (int) config('aicl.notifications.retry.base_delay', 1);
        $exponential = $base * (2 ** ($attempt - 1));
        $jitter = random_int(0, $base * 1000) / 1000;

        return (int) ceil($exponential + $jitter);
    }
}
