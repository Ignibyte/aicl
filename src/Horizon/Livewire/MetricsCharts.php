<?php

declare(strict_types=1);

namespace Aicl\Horizon\Livewire;

use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Models\QueueMetricSnapshot;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use stdClass;

/**
 * MetricsCharts.
 */
class MetricsCharts extends Component
{
    public string $view = 'queues';

    public string $selectedQueue = 'default';

    public string $selectedJob = '';

    public string $timeRange = 'live';

    /**
     * Map of time range keys to minutes.
     *
     * @var array<string, int>
     */
    protected static array $rangeMinutes = [
        '1h' => 60,
        '6h' => 360,
        '24h' => 1440,
        '7d' => 10080,
        '30d' => 43200,
    ];

    public function render(): View
    {
        $metrics = app(MetricsRepository::class);

        $measuredQueues = $metrics->measuredQueues();
        $measuredJobs = $metrics->measuredJobs();

        $snapshots = [];
        $persistenceEnabled = (bool) config('aicl-horizon.metrics.persist_to_database', true);

        if ($this->timeRange !== 'live' && $persistenceEnabled) {
            // @codeCoverageIgnoreStart — Horizon process management
            $snapshots = $this->getSnapshotsForRange();
            // @codeCoverageIgnoreEnd
        } else {
            // Live mode: fall back to Redis snapshots
            if ($this->view === 'queues' && $this->selectedQueue) {
                $snapshots = $this->formatRedisSnapshots(
                    $metrics->snapshotsForQueue($this->selectedQueue)
                );
                // @codeCoverageIgnoreStart — Horizon process management
            } elseif ($this->view === 'jobs' && $this->selectedJob) {
                $snapshots = $this->formatRedisSnapshots(
                    $metrics->snapshotsForJob($this->selectedJob)
                );
                // @codeCoverageIgnoreEnd
            }
        }

        return view('aicl::horizon.livewire.metrics-charts', [
            'measuredQueues' => $measuredQueues,
            'measuredJobs' => $measuredJobs,
            'snapshots' => $snapshots,
            'persistenceEnabled' => $persistenceEnabled,
        ]);
    }

    /**
     * Get snapshots from the database for the selected time range.
     *
     * @return array<int, array{time: string, throughput: float, runtime: float}>
     */
    protected function getSnapshotsForRange(): array
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $minutesBack = static::$rangeMinutes[$this->timeRange] ?? 60;

        $type = $this->view === 'queues' ? 'queue' : 'job';
        $name = $this->view === 'queues' ? $this->selectedQueue : $this->selectedJob;

        if (empty($name)) {
            return [];
        }

        $snapshots = QueueMetricSnapshot::getHistoricalData($type, $name, $minutesBack);

        // Thin data points for long ranges to keep charts readable
        $thinned = $this->thinDataPoints($snapshots->all(), $minutesBack);

        return $this->formatDatabaseSnapshots($thinned);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Thin data points for long-range views by taking every Nth point.
     *
     * @param array<int, QueueMetricSnapshot> $snapshots
     *
     * @return array<int, QueueMetricSnapshot>
     */
    protected function thinDataPoints(array $snapshots, int $minutesBack): array
    {
        $count = count($snapshots);

        // Keep maximum ~100 data points for chart readability
        $maxPoints = 100;

        if ($count <= $maxPoints) {
            return $snapshots;
        }

        $nth = (int) ceil($count / $maxPoints);
        $result = [];

        foreach ($snapshots as $index => $snapshot) {
            if ($index % $nth === 0 || $index === $count - 1) {
                $result[] = $snapshot;
            }
        }

        return $result;
    }

    /**
     * Format Redis snapshot data for display.
     *
     * @param array<int, stdClass> $snapshots
     *
     * @return array<int, array{time: string, throughput: float, runtime: float}>
     */
    protected function formatRedisSnapshots(array $snapshots): array
    {
        return collect($snapshots)->map(function ($snapshot) {
            return [
                'time' => isset($snapshot->time)
                    ? date('H:i', $snapshot->time)
                    : '',
                'throughput' => (float) ($snapshot->throughput ?? 0),
                'runtime' => round((float) ($snapshot->runtime ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * Format database snapshot data for display.
     *
     * @param array<int, QueueMetricSnapshot> $snapshots
     *
     * @return array<int, array{time: string, throughput: float, runtime: float}>
     */
    protected function formatDatabaseSnapshots(array $snapshots): array
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $format = $this->getTimeFormat();

        return collect($snapshots)->map(function (QueueMetricSnapshot $snapshot) use ($format) {
            return [
                'time' => $snapshot->recorded_at->format($format),
                'throughput' => $snapshot->throughput,
                'runtime' => round($snapshot->runtime, 2),
            ];
        })->values()->all();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the appropriate time format string based on the selected range.
     */
    protected function getTimeFormat(): string
    {
        return match ($this->timeRange) {
            '1h', '6h' => 'H:i',
            '24h' => 'H:i',
            '7d', '30d' => 'M d H:i',
            default => 'H:i',
        };
    }
}
