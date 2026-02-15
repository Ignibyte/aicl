<?php

namespace Aicl\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Approvable
{
    public function requestApproval(?User $requester = null, ?string $comment = null): self;

    public function approve(User $approver, ?string $comment = null): self;

    public function reject(User $rejector, string $reason): self;

    public function isPendingApproval(): bool;

    public function isApproved(): bool;

    public function isRejected(): bool;

    public function approvalLogs(): MorphMany;
}
