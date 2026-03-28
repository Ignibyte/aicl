<?php

declare(strict_types=1);

namespace Aicl\Workflows\Events;

use Aicl\Events\DomainEvent;
use Aicl\Events\Enums\ActorType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @codeCoverageIgnore Workflow infrastructure
 */
class ApprovalRequested extends DomainEvent
{
    public static string $eventType = 'approval.requested';

    public Model $approvable;

    public User $requester;

    public ?string $comment;

    public function __construct(
        Model $approvable,
        User $requester,
        ?string $comment = null,
    ) {
        parent::__construct(ActorType::User, $requester->id);

        $this->approvable = $approvable;
        $this->requester = $requester;
        $this->comment = $comment;

        $this->forEntity($approvable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter([
            'action' => 'requested',
            'comment' => $this->comment,
        ]);
    }
}
