<?php

declare(strict_types=1);

namespace Aicl\Horizon\Repositories;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Contracts\WorkloadRepository;
use Aicl\Horizon\WaitTimeCalculator;
use Illuminate\Contracts\Queue\Factory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Str;

/** Redis-backed repository for calculating current queue workload across supervisors. */
class RedisWorkloadRepository implements WorkloadRepository
{
    /**
     * The queue factory implementation.
     *
     * @var Factory
     */
    public $queue;

    /**
     * The wait time calculator instance.
     *
     * @var WaitTimeCalculator
     */
    public $waitTime;

    /**
     * The master supervisor repository implementation.
     *
     * @var MasterSupervisorRepository
     */
    private $masters;

    /**
     * The supervisor repository implementation.
     *
     * @var SupervisorRepository
     */
    private $supervisors;

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        QueueFactory $queue,
        WaitTimeCalculator $waitTime,
        MasterSupervisorRepository $masters,
        SupervisorRepository $supervisors,
    ) {
        $this->queue = $queue;
        $this->masters = $masters;
        $this->waitTime = $waitTime;
        $this->supervisors = $supervisors;
    }

    /**
     * Get the current workload of each queue.
     *
     * @return array<int, array{"name": string, "length": int, "wait": int, "processes": int, "split_queues": null|array<int, array{"name": string, "wait": int, "length": int}>}>
     */
    public function get()
    {
        $processes = $this->processes();

        return collect($this->waitTime->calculate())
            ->map(function ($waitTime, $queue) use ($processes) {
                [$connection, $queueName] = explode(':', $queue, 2);

                $totalProcesses = $processes[$queue] ?? 0;

                $length = ! Str::contains($queue, ',')
                    ? collect([$queueName => $this->queue->connection($connection)->readyNow($queueName)])
                    // @codeCoverageIgnoreStart — Horizon process management
                    : collect(explode(',', $queueName))->mapWithKeys(function ($queueName) use ($connection) {
                        return [$queueName => $this->queue->connection($connection)->readyNow($queueName)];
                    });

                $splitQueues = Str::contains($queue, ',') ? $length->map(function ($length, $queueName) use ($connection, $totalProcesses, &$wait) {
                    return [
                        'name' => $queueName,
                        'length' => $length,
                        'wait' => $wait += $this->waitTime->calculateTimeToClear($connection, $queueName, $totalProcesses),
                    ];
                    // @codeCoverageIgnoreEnd
                }) : null;

                return [
                    'name' => $queueName,
                    'length' => $length->sum(),
                    'wait' => $waitTime,
                    'processes' => $totalProcesses,
                    'split_queues' => $splitQueues,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get the number of processes of each queue.
     *
     * @return array<string, int>
     */
    private function processes()
    {
        /** @var array<string, int> $result */
        $result = collect($this->supervisors->all())
            ->pluck('processes')
            ->reduce(function ($final, $queues) {
                foreach ($queues as $queue => $processes) {
                    $final[$queue] = isset($final[$queue]) ? $final[$queue] + $processes : $processes;
                }

                return $final;
            }, []);

        return $result;
    }
}
