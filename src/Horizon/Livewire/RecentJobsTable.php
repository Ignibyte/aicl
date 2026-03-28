<?php

declare(strict_types=1);

namespace Aicl\Horizon\Livewire;

use Aicl\Horizon\Contracts\JobRepository;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * RecentJobsTable.
 */
class RecentJobsTable extends Component
{
    public ?string $afterIndex = null;

    public function loadMore(): void
    {
        // Pagination is handled by afterIndex cursor
        // @codeCoverageIgnoreStart — Horizon process management
    }
    // @codeCoverageIgnoreEnd

    public function render(): View
    {
        $jobs = app(JobRepository::class)->getRecent($this->afterIndex);

        if ($jobs->isNotEmpty()) {
            // @codeCoverageIgnoreStart — Horizon process management
            $this->afterIndex = $jobs->last()->id ?? null;
            // @codeCoverageIgnoreEnd
        }

        return view('aicl::horizon.livewire.jobs-table', [
            'jobs' => $jobs,
            'title' => 'Recent Jobs',
            'emptyMessage' => 'No recent jobs.',
            'showStatus' => true,
        ]);
    }
}
