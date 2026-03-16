<?php

namespace Aicl\Contracts;

use Aicl\Workflows\Models\ApprovalLog;
use Aicl\Workflows\Traits\RequiresApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract for models that support approval workflows.
 *
 * Implemented by the RequiresApproval trait. Provides methods to request,
 * grant, and reject approval, query approval status, and access the
 * polymorphic approval log relationship.
 *
 * @see RequiresApproval  Trait that implements this contract
 * @see ApprovalLog  Polymorphic log of approval decisions
 */
interface Approvable
{
    /**
     * Submit this model for approval.
     *
     * @param  User|null  $requester  The user requesting approval (defaults to current user)
     * @param  string|null  $comment  Optional comment with the request
     */
    public function requestApproval(?User $requester = null, ?string $comment = null): self;

    /**
     * Approve this model.
     *
     * @param  User  $approver  The user granting approval
     * @param  string|null  $comment  Optional comment
     */
    public function approve(User $approver, ?string $comment = null): self;

    /**
     * Reject this model's approval request.
     *
     * @param  User  $rejector  The user rejecting the request
     * @param  string  $reason  Rejection reason (required)
     */
    public function reject(User $rejector, string $reason): self;

    /**
     * Check if the model is currently pending approval.
     */
    public function isPendingApproval(): bool;

    /**
     * Check if the model has been approved.
     */
    public function isApproved(): bool;

    /**
     * Check if the model has been rejected.
     */
    public function isRejected(): bool;

    /**
     * Get the polymorphic relationship to approval log entries.
     *
     * @return MorphMany<ApprovalLog, Model>
     */
    public function approvalLogs(): MorphMany;
}
