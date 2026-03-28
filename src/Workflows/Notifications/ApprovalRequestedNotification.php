<?php

declare(strict_types=1);

namespace Aicl\Workflows\Notifications;

use Aicl\Notifications\BaseNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @codeCoverageIgnore Notification infrastructure
 */
class ApprovalRequestedNotification extends BaseNotification
{
    public function __construct(
        public Model $approvable,
        public User $requester,
        public ?string $comment = null,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        $type = class_basename($this->approvable);
        $name = $this->approvable->name ?? $this->approvable->title ?? "#{$this->approvable->getKey()}";

        return [
            'title' => "{$type} Approval Requested",
            'body' => "{$this->requester->name} requested approval for {$name}"
                .($this->comment ? ": {$this->comment}" : ''),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'action_url' => '',
            'action_text' => 'Review',
        ];
    }

    public function getIcon(): string
    {
        return 'heroicon-o-clock';
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
