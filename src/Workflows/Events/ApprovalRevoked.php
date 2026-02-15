<?php

namespace Aicl\Workflows\Events;

use Aicl\Events\DomainEvent;
use Aicl\Events\Enums\ActorType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalRevoked extends DomainEvent
{
    public static string $eventType = 'approval.revoked';

    public Model $approvable;

    public User $revoker;

    public string $reason;

    public function __construct(
        Model $approvable,
        User $revoker,
        string $reason,
    ) {
        parent::__construct(ActorType::User, $revoker->id);

        $this->approvable = $approvable;
        $this->revoker = $revoker;
        $this->reason = $reason;

        $this->forEntity($approvable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'action' => 'revoked',
            'reason' => $this->reason,
        ];
    }
}
