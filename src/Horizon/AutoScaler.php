<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Aicl\Horizon\Contracts\MetricsRepository;
use Illuminate\Contracts\Queue\Factory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Collection;

/** Dynamically adjusts worker process counts based on queue workload metrics. */
class AutoScaler
{
    /**
     * The queue factory implementation.
     *
     * @var Factory
     */
    public $queue;

    /**
     * The metrics repository implementation.
     *
     * @var MetricsRepository
     */
    public $metrics;

    /**
     * Create a new auto-scaler instance.
     *
     * @return void
     */
    public function __construct(QueueFactory $queue, MetricsRepository $metrics)
    {
        $this->queue = $queue;
        $this->metrics = $metrics;
    }

    /**
     * Balance the workers on the given supervisor.
     *
     * @return void
     */
    public function scale(Supervisor $supervisor)
    {
        $pools = $this->poolsByQueue($supervisor);

        $workers = $this->numberOfWorkersPerQueue(
            $supervisor, $this->timeToClearPerQueue($supervisor, $pools)
        );

        $workers->each(function ($workers, $queue) use ($supervisor, $pools) {
            $pool = $pools[$queue] ?? null;

            if ($pool === null) {
                return;
            }

            $this->scalePool($supervisor, $pool, $workers);
        });
    }

    /**
     * Get the process pools keyed by their queue name.
     *
     * @return Collection<string, ProcessPool>
     */
    protected function poolsByQueue(Supervisor $supervisor)
    {
        return $supervisor->processPools->mapWithKeys(function ($pool) {
            return [$pool->queue() => $pool];
        });
    }

    /**
     * Get the times in milliseconds needed to clear the queues.
     *
     * @param  Collection<string, ProcessPool>  $pools
     * @return Collection<string, array{size: mixed, time: int}>
     */
    protected function timeToClearPerQueue(Supervisor $supervisor, Collection $pools)
    {
        return $pools->mapWithKeys(function ($pool, $queue) use ($supervisor) {
            $queues = collect(explode(',', $queue))->map(function ($_queue) use ($supervisor) {
                $size = $this->queue->connection($supervisor->options->connection)->readyNow($_queue);

                return [
                    'size' => $size,
                    'time' => ($size * $this->metrics->runtimeForQueue($_queue)),
                ];
            });

            return [$queue => [
                'size' => $queues->sum('size'),
                'time' => $queues->sum('time'),
            ]];
        });
    }

    /**
     * Get the number of workers needed per queue for proper balance.
     *
     * @param  Collection<string, array{size: mixed, time: int}>  $queues
     * @return Collection<string, float|int>
     */
    protected function numberOfWorkersPerQueue(Supervisor $supervisor, Collection $queues)
    {
        $timeToClearAll = $queues->sum('time');
        $totalJobs = $queues->sum('size');

        return $queues->mapWithKeys(function ($timeToClear, $queue) use ($supervisor, $timeToClearAll, $totalJobs) {
            if (! $supervisor->options->balancing()) {
                $targetProcesses = min(
                    $supervisor->options->maxProcesses,
                    max($supervisor->options->minProcesses, $timeToClear['size'])
                );

                return [$queue => $targetProcesses];
            }

            if ($timeToClearAll > 0 &&
                $supervisor->options->autoScaling()) {
                $numberOfProcesses = $supervisor->options->autoScaleByNumberOfJobs()
                    ? ($timeToClear['size'] / $totalJobs)
                    : ($timeToClear['time'] / $timeToClearAll);

                return [$queue => $numberOfProcesses *= $supervisor->options->maxProcesses];
            } elseif ($timeToClearAll == 0 &&
                      $supervisor->options->autoScaling()) {
                return [
                    $queue => $timeToClear['size']
                        ? $supervisor->options->maxProcesses
                        : $supervisor->options->minProcesses,
                ];
            }

            return [$queue => $supervisor->options->maxProcesses / count($supervisor->processPools)];
        })->sort();
    }

    /**
     * Scale the given pool to the recommended number of workers.
     *
     * @param  ProcessPool  $pool
     * @param  float  $workers
     * @return void
     */
    protected function scalePool(Supervisor $supervisor, $pool, $workers)
    {
        $supervisor->pruneTerminatingProcesses();

        $totalProcessCount = $pool->totalProcessCount();

        $desiredProcessCount = (int) ceil((float) $workers);

        if ($desiredProcessCount > $totalProcessCount) {
            $maxUpShift = min(
                max(0, $supervisor->options->maxProcesses - $supervisor->totalProcessCount()),
                $supervisor->options->balanceMaxShift
            );

            $pool->scale((int) min(
                $totalProcessCount + $maxUpShift,
                max($supervisor->options->minProcesses, $supervisor->options->maxProcesses - (($supervisor->processPools->count() - 1) * $supervisor->options->minProcesses)),
                $desiredProcessCount
            ));
        } elseif ($desiredProcessCount < $totalProcessCount) {
            $maxDownShift = min(
                $supervisor->totalProcessCount() - $supervisor->options->minProcesses,
                $supervisor->options->balanceMaxShift
            );

            $pool->scale((int) max(
                $totalProcessCount - $maxDownShift,
                $supervisor->options->minProcesses,
                $desiredProcessCount
            ));
        }
    }
}
