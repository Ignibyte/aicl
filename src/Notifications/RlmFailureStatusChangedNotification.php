<?php

namespace Aicl\Notifications;

use Aicl\Models\RlmFailure;
use Aicl\States\RlmFailureState;
use App\Models\User;

class RlmFailureStatusChangedNotification extends BaseNotification
{
    public function __construct(
        public RlmFailure $rlm_failure,
        public RlmFailureState $previousStatus,
        public RlmFailureState $newStatus,
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
            'title' => 'Failure status changed',
            'body' => "Failure \"{$this->rlm_failure->failure_code}: {$this->rlm_failure->title}\" status changed from {$this->previousStatus->label()} to {$this->newStatus->label()}{$changedByText}.",
            'icon' => 'heroicon-o-arrow-path',
            'color' => $this->newStatus->color(),
            'action_url' => route('filament.admin.resources.rlm_failures.view', ['record' => $this->rlm_failure]),
            'action_text' => 'View Failure',
        ];
    }

    public function getIcon(): string
    {
        return 'heroicon-o-arrow-path';
    }

    public function getColor(): string
    {
        return $this->newStatus->color();
    }
}
