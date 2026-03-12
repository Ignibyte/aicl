<?php

namespace Aicl\Horizon\Livewire;

use Aicl\Horizon\Contracts\MetricsRepository;
use Livewire\Component;

class MetricsCharts extends Component
{
    public string $view = 'queues';

    public string $selectedQueue = 'default';

    public string $selectedJob = '';

    public function render()
    {
        $metrics = app(MetricsRepository::class);

        $measuredQueues = $metrics->measuredQueues();
        $measuredJobs = $metrics->measuredJobs();

        $queueSnapshots = [];
        $jobSnapshots = [];

        if ($this->view === 'queues' && $this->selectedQueue) {
            $queueSnapshots = $this->formatSnapshots(
                $metrics->snapshotsForQueue($this->selectedQueue)
            );
        } elseif ($this->view === 'jobs' && $this->selectedJob) {
            $jobSnapshots = $this->formatSnapshots(
                $metrics->snapshotsForJob($this->selectedJob)
            );
        }

        return view('aicl::horizon.livewire.metrics-charts', [
            'measuredQueues' => $measuredQueues,
            'measuredJobs' => $measuredJobs,
            'snapshots' => $this->view === 'queues' ? $queueSnapshots : $jobSnapshots,
        ]);
    }

    /**
     * Format snapshot data for display.
     *
     * @return array<int, array{time: string, throughput: float, runtime: float}>
     */
    protected function formatSnapshots(array $snapshots): array
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
}
