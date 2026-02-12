<?php

// PATTERN: Entity notification extends BaseNotification.
// PATTERN: Uses constructor property promotion for dependencies.
// PATTERN: toDatabase() returns structured data for the notification center.
// PATTERN: getIcon() and getColor() used by the notification UI.

namespace Aicl\Notifications;

use App\Models\Project;
use App\Models\User;

class ProjectAssignedNotification extends BaseNotification
{
    // PATTERN: Constructor property promotion with public visibility.
    public function __construct(
        public Project $project,
        public User $assignedBy,
    ) {}

    /**
     * PATTERN: toDatabase() returns structured notification data.
     * Required keys: title, body, icon, color, action_url, action_text.
     * Additional keys are entity-specific metadata.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'You have been assigned to a project',
            'body' => "You have been assigned as the owner of \"{$this->project->name}\" by {$this->assignedBy->name}.",
            'icon' => 'heroicon-o-briefcase',
            'color' => 'primary',
            // PATTERN: action_url uses named route for the Filament resource view page.
            'action_url' => route('filament.admin.resources.projects.view', ['record' => $this->project]),
            'action_text' => 'View Project',
            // PATTERN: Additional entity metadata for filtering/display.
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'assigned_by_id' => $this->assignedBy->id,
            'assigned_by_name' => $this->assignedBy->name,
        ];
    }

    public function getIcon(): string
    {
        return 'heroicon-o-briefcase';
    }

    public function getColor(): string
    {
        return 'primary';
    }
}
