<?php

declare(strict_types=1);

namespace Aicl\Horizon\Contracts;

use Aicl\Horizon\JobPayload;
use Exception;
use Illuminate\Support\Collection;
use stdClass;

/**
 * JobRepository.
 */
interface JobRepository
{
    /**
     * Get the next job ID that should be assigned.
     *
     * @return string
     */
    public function nextJobId();

    /**
     * Get the total count of recent jobs.
     *
     * @return int
     */
    public function totalRecent();

    /**
     * Get the total count of failed jobs.
     *
     * @return int
     */
    public function totalFailed();

    /**
     * Get a chunk of recent jobs.
     *
     * @param string|null $afterIndex
     *
     * @return Collection<int, stdClass>
     */
    public function getRecent($afterIndex = null);

    /**
     * Get a chunk of failed jobs.
     *
     * @param string|null $afterIndex
     *
     * @return Collection<int, stdClass>
     */
    public function getFailed($afterIndex = null);

    /**
     * Get a chunk of pending jobs.
     *
     * @param string|null $afterIndex
     *
     * @return Collection<int, stdClass>
     */
    public function getPending($afterIndex = null);

    /**
     * Get a chunk of completed jobs.
     *
     * @param string|null $afterIndex
     *
     * @return Collection<int, stdClass>
     */
    public function getCompleted($afterIndex = null);

    /**
     * Get a chunk of silenced jobs.
     *
     * @param string|null $afterIndex
     *
     * @return Collection<int, stdClass>
     */
    public function getSilenced($afterIndex = null);

    /**
     * Get the count of recent jobs.
     *
     * @return int
     */
    public function countRecent();

    /**
     * Get the count of failed jobs.
     *
     * @return int
     */
    public function countFailed();

    /**
     * Get the count of pending jobs.
     *
     * @return int
     */
    public function countPending();

    /**
     * Get the count of completed jobs.
     *
     * @return int
     */
    public function countCompleted();

    /**
     * Get the count of silenced jobs.
     *
     * @return int
     */
    public function countSilenced();

    /**
     * Get the count of the recently failed jobs.
     *
     * @return int
     */
    public function countRecentlyFailed();

    /**
     * Retrieve the jobs with the given IDs.
     *
     * @param array<int, string> $ids
     * @param mixed              $indexFrom
     *
     * @return Collection<int, stdClass>
     */
    public function getJobs(array $ids, $indexFrom = 0);

    /**
     * Insert the job into storage.
     *
     * @param string $connection
     * @param string $queue
     */
    public function pushed($connection, $queue, JobPayload $payload);

    /**
     * Mark the job as reserved.
     *
     * @param string $connection
     * @param string $queue
     */
    public function reserved($connection, $queue, JobPayload $payload);

    /**
     * Mark the job as released / pending.
     *
     * @param string $connection
     * @param string $queue
     * @param int    $delay
     */
    public function released($connection, $queue, JobPayload $payload, $delay = 0);

    /**
     * Mark the job as completed and monitored.
     *
     * @param string $connection
     * @param string $queue
     */
    public function remember($connection, $queue, JobPayload $payload);

    /**
     * Mark the given jobs as released / pending.
     *
     * @param string                      $connection
     * @param string                      $queue
     * @param Collection<int, JobPayload> $payloads
     */
    public function migrated($connection, $queue, Collection $payloads);

    /**
     * Handle the storage of a completed job.
     *
     * @param bool $failed
     * @param bool $silenced
     */
    public function completed(JobPayload $payload, $failed = false, $silenced = false);

    /**
     * Delete the given monitored jobs by IDs.
     *
     * @param array<int, string> $ids
     */
    public function deleteMonitored(array $ids);

    /**
     * Trim the recent job list.
     */
    public function trimRecentJobs();

    /**
     * Trim the failed job list.
     */
    public function trimFailedJobs();

    /**
     * Trim the monitored job list.
     */
    public function trimMonitoredJobs();

    /**
     * Find a failed job by ID.
     *
     * @param string $id
     *
     * @return stdClass|null
     */
    public function findFailed($id);

    /**
     * Mark the job as failed.
     *
     * @param Exception $exception
     * @param string    $connection
     * @param string    $queue
     */
    public function failed($exception, $connection, $queue, JobPayload $payload);

    /**
     * Store the retry job ID on the original job record.
     *
     * @param string $id
     * @param string $retryId
     */
    public function storeRetryReference($id, $retryId);

    /**
     * Delete a failed job by ID.
     *
     * @param string $id
     *
     * @return int
     */
    public function deleteFailed($id);
}
