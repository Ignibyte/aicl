<?php

namespace Aicl\Horizon\Livewire;

use Aicl\Horizon\Contracts\JobRepository;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CompletedJobsTable extends Component
{
    public ?string $afterIndex = null;

    public function render(): View
    {
        $jobs = app(JobRepository::class)->getCompleted($this->afterIndex);

        if ($jobs->isNotEmpty()) {
            $this->afterIndex = $jobs->last()->id ?? null;
        }

        return view('aicl::horizon.livewire.jobs-table', [
            'jobs' => $jobs,
            'title' => 'Completed Jobs',
            'emptyMessage' => 'No completed jobs.',
            'showStatus' => false,
        ]);
    }
}
