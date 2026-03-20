<?php

declare(strict_types=1);

namespace Aicl\Events;

use Aicl\Events\Enums\ActorType;

/** Domain event dispatched when an admin terminates another user's session. */
class SessionTerminated extends DomainEvent
{
    public static string $eventType = 'session.terminated';

    public string $terminatedSessionId;

    public int $terminatedUserId;

    public string $terminatedUserName;

    public function __construct(
        string $terminatedSessionId,
        int $terminatedUserId,
        string $terminatedUserName,
    ) {
        parent::__construct(ActorType::User, (int) auth()->id());

        $this->terminatedSessionId = $terminatedSessionId;
        $this->terminatedUserId = $terminatedUserId;
        $this->terminatedUserName = $terminatedUserName;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'action' => 'session_terminated',
            'terminated_session_id' => $this->terminatedSessionId,
            'terminated_user_id' => $this->terminatedUserId,
            'terminated_user_name' => $this->terminatedUserName,
        ];
    }
}
