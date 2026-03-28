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
class ApprovalGranted extends DomainEvent
{
    public static string $eventType = 'approval.granted';

    public Model $approvable;

    public User $approver;

    public ?string $comment;

    public function __construct(
        Model $approvable,
        User $approver,
        ?string $comment = null,
    ) {
        parent::__construct(ActorType::User, $approver->id);

        $this->approvable = $approvable;
        $this->approver = $approver;
        $this->comment = $comment;

        $this->forEntity($approvable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter([
            'action' => 'granted',
            'comment' => $this->comment,
        ]);
    }
}
