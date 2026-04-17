<?php

declare(strict_types=1);

namespace Aicl\Horizon\Jobs;

use Aicl\Horizon\Contracts\JobRepository;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Factory as Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Retries a failed Horizon job by re-pushing it onto its original queue.
 *
 * Implemented as a truly queued job so the admin "Retry" click in
 * FailedJobsTable returns an immediate HTTP response; the actual re-push
 * happens on a worker. SerializesModels is safe here because the public
 * properties are scalar strings.
 */
class RetryFailedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of retry attempts on worker failure.
     */
    public int $tries = 3;

    /**
     * Worker-side timeout in seconds.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     *
     * @param string $id The job ID.
     */
    public function __construct(
        public $id,
    ) {}

    /**
     * Execute the job.
     *
     * @SuppressWarnings(PHPMD.IfStatementAssignment)
     */
    public function handle(Queue $queue, JobRepository $jobs)
    {
        if (is_null($job = $jobs->findFailed($this->id))) {
            return;
        }

        $queue->connection($job->connection)->pushRaw(
            $this->preparePayload($id = (string) Str::uuid(), $job->payload), $job->queue
        );

        $jobs->storeRetryReference($this->id, $id);
    }

    /**
     * Prepare the payload for queueing.
     *
     * @param string $id
     * @param string $payload
     *
     * @return string
     */
    protected function preparePayload($id, $payload)
    {
        $payload = json_decode($payload, true);

        $encoded = json_encode(array_merge($payload, [
            'id' => $id,
            'uuid' => $id,
            'attempts' => 0,
            'retry_of' => $this->id,
            'retryUntil' => $this->prepareNewTimeout($payload),
        ]));

        return ($encoded !== false && $encoded !== '') ? $encoded : '{}';
    }

    /**
     * Prepare the timeout.
     *
     * @param array<string, mixed> $payload
     *
     * @return int|null
     */
    protected function prepareNewTimeout($payload)
    {
        $retryUntil = $payload['retryUntil'] ?? $payload['timeoutAt'] ?? null;

        $pushedAt = $payload['pushedAt'] ?? microtime(true);

        return $retryUntil !== null
            ? CarbonImmutable::now()->addSeconds(ceil($retryUntil - $pushedAt))->getTimestamp()
            : null;
    }
}
