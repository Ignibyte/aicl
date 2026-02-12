<?php

namespace Aicl\Notifications;

use Aicl\Models\RlmFailure;
use App\Models\User;

class RlmFailureAssignedNotification extends BaseNotification
{
    public function __construct(
        public RlmFailure $rlm_failure,
        public User $assignedBy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Failure assigned to you',
            'body' => "Failure \"{$this->rlm_failure->failure_code}: {$this->rlm_failure->title}\" was assigned to you by {$this->assignedBy->name}.",
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'action_url' => route('filament.admin.resources.rlm_failures.view', ['record' => $this->rlm_failure]),
            'action_text' => 'View Failure',
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
