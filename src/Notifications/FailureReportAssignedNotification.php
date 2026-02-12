<?php

namespace Aicl\Notifications;

use Aicl\Models\FailureReport;
use App\Models\User;

class FailureReportAssignedNotification extends BaseNotification
{
    public function __construct(
        public FailureReport $failure_report,
        public User $assignedBy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Failure report assigned to you',
            'body' => "Report for \"{$this->failure_report->entity_name}\" (project {$this->failure_report->project_hash}) was assigned to you by {$this->assignedBy->name}.",
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'action_url' => route('filament.admin.resources.failure-reports.view', ['record' => $this->failure_report]),
            'action_text' => 'View Report',
        ];
    }

    public function getIcon(): string
    {
        return 'heroicon-o-user-plus';
    }

    public function getColor(): string
    {
        return 'primary';
    }
}
