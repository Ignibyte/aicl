<?php

declare(strict_types=1);

namespace Aicl\Workflows\Traits;

use Aicl\Services\NotificationDispatcher;
use Aicl\Workflows\Enums\ApprovalStatus;
use Aicl\Workflows\Events\ApprovalGranted;
use Aicl\Workflows\Events\ApprovalRejected;
use Aicl\Workflows\Events\ApprovalRequested;
use Aicl\Workflows\Events\ApprovalRevoked;
use Aicl\Workflows\Exceptions\ApprovalException;
use Aicl\Workflows\Models\ApprovalLog;
use Aicl\Workflows\Notifications\ApprovalDecisionNotification;
use Aicl\Workflows\Notifications\ApprovalRequestedNotification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @codeCoverageIgnore Workflow infrastructure
 */
trait RequiresApproval
{
    public function initializeRequiresApproval(): void
    {
        if (! isset($this->casts[$this->getApprovalStatusColumn()])) {
            $this->casts[$this->getApprovalStatusColumn()] = ApprovalStatus::class;
        }
    }

    /**
     * Request approval for this model.
     *
     * @throws ApprovalException If model is already pending or approved
     */
    public function requestApproval(?User $requester = null, ?string $comment = null): self
    {
        $requester ??= auth()->user();
        $status = $this->getApprovalStatusValue();

        if ($status === ApprovalStatus::Pending) {
            throw ApprovalException::alreadyPending($this);
        }

        if ($status === ApprovalStatus::Approved) {
            throw ApprovalException::alreadyApproved($this);
        }

        $fromStatus = $status->value;

        $this->setAttribute($this->getApprovalStatusColumn(), ApprovalStatus::Pending);
        $this->save();

        $this->logApprovalAction($requester, 'requested', $fromStatus, ApprovalStatus::Pending->value, $comment);

        ApprovalRequested::dispatch($this, $requester, $comment);

        $this->notifyApprovers($requester, $comment);

        return $this;
    }

    /**
     * Approve this model.
     *
     * @throws AuthorizationException If user lacks permission
     * @throws ApprovalException      If model is not pending
     */
    public function approve(User $approver, ?string $comment = null): self
    {
        $this->authorizeApprovalAction($approver);

        if ($this->getApprovalStatusValue() !== ApprovalStatus::Pending) {
            throw ApprovalException::notPending($this);
        }

        $this->setAttribute($this->getApprovalStatusColumn(), ApprovalStatus::Approved);
        $this->save();

        $this->logApprovalAction($approver, 'approved', ApprovalStatus::Pending->value, ApprovalStatus::Approved->value, $comment);

        ApprovalGranted::dispatch($this, $approver, $comment);

        $this->notifyRequester($approver, ApprovalStatus::Approved, $comment);

        return $this;
    }

    /**
     * Reject this model.
     *
     * @throws AuthorizationException If user lacks permission
     * @throws ApprovalException      If model is not pending
     */
    public function reject(User $rejector, string $reason): self
    {
        $this->authorizeApprovalAction($rejector);

        if ($this->getApprovalStatusValue() !== ApprovalStatus::Pending) {
            throw ApprovalException::notPending($this);
        }

        $this->setAttribute($this->getApprovalStatusColumn(), ApprovalStatus::Rejected);
        $this->save();

        $this->logApprovalAction($rejector, 'rejected', ApprovalStatus::Pending->value, ApprovalStatus::Rejected->value, $reason);

        ApprovalRejected::dispatch($this, $rejector, $reason);

        $this->notifyRequester($rejector, ApprovalStatus::Rejected, $reason);

        return $this;
    }

    /**
     * Revoke a previous approval.
     *
     * @throws AuthorizationException If user lacks permission
     * @throws ApprovalException      If model is not approved
     */
    public function revokeApproval(User $revoker, string $reason): self
    {
        $this->authorizeApprovalAction($revoker);

        if ($this->getApprovalStatusValue() !== ApprovalStatus::Approved) {
            throw ApprovalException::notApproved($this);
        }

        $this->setAttribute($this->getApprovalStatusColumn(), ApprovalStatus::Pending);
        $this->save();

        $this->logApprovalAction($revoker, 'revoked', ApprovalStatus::Approved->value, ApprovalStatus::Pending->value, $reason);

        ApprovalRevoked::dispatch($this, $revoker, $reason);

        return $this;
    }

    // -- Status Checks --

    public function isPendingApproval(): bool
    {
        return $this->getApprovalStatusValue() === ApprovalStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->getApprovalStatusValue() === ApprovalStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->getApprovalStatusValue() === ApprovalStatus::Rejected;
    }

    // -- Relationships --

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }

    // -- Scopes --

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where($this->getApprovalStatusColumn(), ApprovalStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where($this->getApprovalStatusColumn(), ApprovalStatus::Approved);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where($this->getApprovalStatusColumn(), ApprovalStatus::Rejected);
    }

    // -- Configuration (overridable) --

    /**
     * Column name for approval status.
     */
    public function getApprovalStatusColumn(): string
    {
        return 'approval_status';
    }

    /**
     * Permission name required to approve/reject this model.
     */
    public function getApprovalPermission(): string
    {
        return 'Approve:'.class_basename($this);
    }

    /**
     * Users who should be notified when approval is requested.
     * Default: users with the approval permission.
     *
     * @return Collection<int, User>
     */
    public function getApprovers(): Collection
    {
        return User::permission($this->getApprovalPermission())->get();
    }

    // -- Private Helpers --

    private function getApprovalStatusValue(): ApprovalStatus
    {
        $value = $this->getAttribute($this->getApprovalStatusColumn());

        if ($value instanceof ApprovalStatus) {
            return $value;
        }

        return ApprovalStatus::from($value ?? ApprovalStatus::Draft->value);
    }

    private function authorizeApprovalAction(User $user): void
    {
        if (! $user->can($this->getApprovalPermission())) {
            throw new AuthorizationException(
                "User does not have permission: {$this->getApprovalPermission()}"
            );
        }
    }

    private function logApprovalAction(
        User $actor,
        string $action,
        string $fromStatus,
        string $toStatus,
        ?string $comment,
    ): void {
        $this->approvalLogs()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comment' => $comment,
        ]);
    }

    private function notifyApprovers(User $requester, ?string $comment): void
    {
        $approvers = $this->getApprovers();

        if ($approvers->isEmpty()) {
            return;
        }

        $notification = new ApprovalRequestedNotification($this, $requester, $comment);
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->sendToMany($approvers, $notification, $requester);
    }

    private function notifyRequester(User $decider, ApprovalStatus $decision, ?string $comment): void
    {
        // Find the original requester from the approval logs
        $requestLog = $this->approvalLogs()
            ->where('action', 'requested')
            ->latest()
            ->first();

        if (! $requestLog) {
            return;
        }

        $requester = User::find($requestLog->actor_id);

        if (! $requester || $requester->id === $decider->id) {
            return;
        }

        $notification = new ApprovalDecisionNotification($this, $decider, $decision, $comment);
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->send($requester, $notification, $decider);
    }
}
