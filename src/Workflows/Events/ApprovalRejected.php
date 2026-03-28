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
class ApprovalRejected extends DomainEvent
{
    public static string $eventType = 'approval.rejected';

    public Model $approvable;

    public User $rejector;

    public string $reason;

    public function __construct(
        Model $approvable,
        User $rejector,
        string $reason,
    ) {
        parent::__construct(ActorType::User, $rejector->id);

        $this->approvable = $approvable;
        $this->rejector = $rejector;
        $this->reason = $reason;

        $this->forEntity($approvable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'action' => 'rejected',
            'reason' => $this->reason,
        ];
    }
}
