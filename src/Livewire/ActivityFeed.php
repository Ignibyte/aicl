<?php

namespace Aicl\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Real-time activity feed with polling and pagination.
 *
 * AI Decision Rules:
 * - Use on dashboards to show recent system activity
 * - Use on entity detail pages to show entity-specific activity
 * - Set causer-type/id to filter by user actions
 * - Set subject-type/id to filter by entity actions
 * - Polling interval defaults to 30s; set to 0 to disable
 */
class ActivityFeed extends Component
{
    use WithPagination;

    public int $perPage = 10;

    public int $pollInterval = 30;

    public ?string $subjectType = null;

    public ?int $subjectId = null;

    public ?string $causerType = null;

    public ?int $causerId = null;

    public ?string $logName = null;

    public string $heading = 'Recent Activity';

    public bool $showCauser = true;

    public bool $showSubject = true;

    #[On('entity-changed')]
    public function onEntityChanged(): void
    {
        unset($this->activities);
    }

    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        $query = Activity::query()->latest();

        if ($this->subjectType) {
            $query->where('subject_type', $this->subjectType);
        }

        if ($this->subjectId) {
            $query->where('subject_id', $this->subjectId);
        }

        if ($this->causerType) {
            $query->where('causer_type', $this->causerType);
        }

        if ($this->causerId) {
            $query->where('causer_id', $this->causerId);
        }

        if ($this->logName) {
            $query->where('log_name', $this->logName);
        }

        return $query->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('aicl::livewire.activity-feed');
    }
}
