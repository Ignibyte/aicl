<?php

declare(strict_types=1);

namespace Aicl\Horizon\Livewire;

use Aicl\Horizon\Contracts\JobRepository;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * PendingJobsTable.
 */
class PendingJobsTable extends Component
{
    public ?string $afterIndex = null;

    public function render(): View
    {
        $jobs = app(JobRepository::class)->getPending($this->afterIndex);

        if ($jobs->isNotEmpty()) {
            // @codeCoverageIgnoreStart — Horizon process management
            $this->afterIndex = $jobs->last()->id ?? null;
            // @codeCoverageIgnoreEnd
        }

        return view('aicl::horizon.livewire.jobs-table', [
            'jobs' => $jobs,
            'title' => 'Pending Jobs',
            'emptyMessage' => 'No pending jobs.',
            'showStatus' => false,
        ]);
    }
}
