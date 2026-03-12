<?php

namespace Aicl\Horizon\Livewire;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Jobs\RetryFailedJob;
use Filament\Notifications\Notification;
use Livewire\Component;

class FailedJobsTable extends Component
{
    public ?string $afterIndex = null;

    public function retry(string $id): void
    {
        dispatch(new RetryFailedJob($id));

        Notification::make()
            ->success()
            ->title('Job Queued for Retry')
            ->body("Job {$id} has been queued for retry.")
            ->send();
    }

    public function deleteJob(string $id): void
    {
        app(JobRepository::class)->deleteFailed($id);

        Notification::make()
            ->success()
            ->title('Job Deleted')
            ->body("Failed job {$id} has been deleted.")
            ->send();
    }

    public function render()
    {
        $jobs = app(JobRepository::class)->getFailed($this->afterIndex);

        if ($jobs->isNotEmpty()) {
            $this->afterIndex = $jobs->last()->id ?? null;
        }

        return view('aicl::horizon.livewire.failed-jobs-table', [
            'jobs' => $jobs,
        ]);
    }
}
