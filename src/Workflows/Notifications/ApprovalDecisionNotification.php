<?php

declare(strict_types=1);

namespace Aicl\Workflows\Notifications;

use Aicl\Notifications\BaseNotification;
use Aicl\Workflows\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @codeCoverageIgnore Notification infrastructure
 */
class ApprovalDecisionNotification extends BaseNotification
{
    public function __construct(
        public Model $approvable,
        public User $decider,
        public ApprovalStatus $decision,
        public ?string $comment = null,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        $type = class_basename($this->approvable);
        $name = $this->approvable->name ?? $this->approvable->title ?? "#{$this->approvable->getKey()}";
        $action = $this->decision === ApprovalStatus::Approved ? 'approved' : 'rejected';

        return [
            'title' => "{$type} {$this->decision->label()}",
            'body' => "{$this->decider->name} {$action} {$name}"
                .($this->comment ? ": {$this->comment}" : ''),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'action_url' => '',
            'action_text' => 'View',
        ];
    }

    public function getIcon(): string
    {
        return $this->decision === ApprovalStatus::Approved
            ? 'heroicon-o-check-circle'
            : 'heroicon-o-x-circle';
    }

    public function getColor(): string
    {
        return $this->decision === ApprovalStatus::Approved
            ? 'success'
            : 'danger';
    }
}
