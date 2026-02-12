<?php

// PATTERN: Status change notification includes previous and new states.
// PATTERN: Icon and color are dynamic based on the new status.

namespace Aicl\Notifications;

use Aicl\States\ProjectState;
use App\Models\Project;
use App\Models\User;

class ProjectStatusChangedNotification extends BaseNotification
{
    public function __construct(
        public Project $project,
        public ProjectState $previousStatus,
        public ProjectState $newStatus,
        public ?User $changedBy = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $changedByText = $this->changedBy
            ? " by {$this->changedBy->name}"
            : '';

        return [
            'title' => 'Project status changed',
            'body' => "The status of \"{$this->project->name}\" was changed from {$this->previousStatus->label()} to {$this->newStatus->label()}{$changedByText}.",
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'action_url' => route('filament.admin.resources.projects.view', ['record' => $this->project]),
            'action_text' => 'View Project',
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'previous_status' => (string) $this->previousStatus,
            'new_status' => (string) $this->newStatus,
            'changed_by_id' => $this->changedBy?->id,
            'changed_by_name' => $this->changedBy?->name,
        ];
    }

    // PATTERN: Dynamic icon based on the target state.
    public function getIcon(): string
    {
        return match ($this->newStatus::class) {
            \Aicl\States\Active::class => 'heroicon-o-play',
            \Aicl\States\Completed::class => 'heroicon-o-check-circle',
            \Aicl\States\OnHold::class => 'heroicon-o-pause',
            \Aicl\States\Archived::class => 'heroicon-o-archive-box',
            default => 'heroicon-o-arrow-path',
        };
    }

    // PATTERN: Dynamic color from the state's color() method.
    public function getColor(): string
    {
        return $this->newStatus->color();
    }
}
