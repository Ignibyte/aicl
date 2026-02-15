# Approval Workflow Engine

Trait-based approval workflow for any Eloquent model with RBAC, audit logging, and notifications.

**Namespace:** `Aicl\Workflows\Traits\RequiresApproval`
**Location:** `packages/aicl/src/Workflows/`

## Quick Start

### 1. Add the column to your migration

```php
$table->string('approval_status')->default('draft')->index();
```

### 2. Add the trait and contract to your model

```php
use Aicl\Contracts\Approvable;
use Aicl\Workflows\Traits\RequiresApproval;

class Expense extends Model implements Approvable
{
    use RequiresApproval;
}
```

### 3. Seed the approval permission

```php
Permission::findOrCreate('Approve:Expense', 'web');
$adminRole->givePermissionTo('Approve:Expense');
```

### 4. Use it

```php
$expense->requestApproval($user, 'Q4 budget request');
$expense->approve($manager, 'Approved for Q4');
```

---

## Status Flow

```
draft ──→ pending ──→ approved
              │           │
              │           └──→ pending (revoke)
              │
              └──→ rejected ──→ pending (re-submit)
```

| Status | Enum | Color | Icon |
|--------|------|-------|------|
| Draft | `ApprovalStatus::Draft` | gray | pencil-square |
| Pending | `ApprovalStatus::Pending` | warning | clock |
| Approved | `ApprovalStatus::Approved` | success | check-circle |
| Rejected | `ApprovalStatus::Rejected` | danger | x-circle |

---

## API Reference

### `requestApproval(?User $requester = null, ?string $comment = null): self`

Submit for approval. Sets status to `pending`, creates audit log, notifies approvers.

```php
$expense->requestApproval($user, 'Please review Q4 budget');
```

**Throws:**
- `ApprovalException` if already pending or approved

**Valid from:** `draft`, `rejected`

---

### `approve(User $approver, ?string $comment = null): self`

Approve a pending record. Checks RBAC, creates audit log, fires event, notifies requester.

```php
$expense->approve($manager, 'Looks good');
```

**Throws:**
- `AuthorizationException` if user lacks `Approve:{Model}` permission
- `ApprovalException` if not pending

---

### `reject(User $rejector, string $reason): self`

Reject a pending record. Reason is required.

```php
$expense->reject($manager, 'Missing receipts');
```

**Throws:**
- `AuthorizationException` if user lacks permission
- `ApprovalException` if not pending

---

### `revokeApproval(User $revoker, string $reason): self`

Revoke a previous approval. Returns to pending.

```php
$expense->revokeApproval($manager, 'New budget constraints');
```

**Throws:**
- `AuthorizationException` if user lacks permission
- `ApprovalException` if not approved

---

### Status Checks

```php
$expense->isPendingApproval();  // true if pending
$expense->isApproved();          // true if approved
$expense->isRejected();          // true if rejected
```

---

### Scopes

```php
Expense::pendingApproval()->get();
Expense::approved()->get();
Expense::rejected()->get();
```

---

### Audit Trail

```php
$expense->approvalLogs; // Collection of ApprovalLog entries

// Each log has:
$log->actor;       // User who performed the action
$log->action;      // 'requested', 'approved', 'rejected', 'revoked'
$log->from_status; // Previous status
$log->to_status;   // New status
$log->comment;     // Comment or reason
$log->created_at;  // When it happened
```

---

## Events

| Event | Fired When | Properties |
|-------|-----------|------------|
| `ApprovalRequested` | `requestApproval()` | `$approvable`, `$requester`, `$comment` |
| `ApprovalGranted` | `approve()` | `$approvable`, `$approver`, `$comment` |
| `ApprovalRejected` | `reject()` | `$approvable`, `$rejector`, `$reason` |
| `ApprovalRevoked` | `revokeApproval()` | `$approvable`, `$revoker`, `$reason` |

```php
Event::listen(ApprovalGranted::class, function (ApprovalGranted $event) {
    // Publish content, activate resource, etc.
    $event->approvable->update(['published_at' => now()]);
});
```

---

## Notifications

Two notifications are dispatched automatically:

**`ApprovalRequestedNotification`** — sent to all approvers (users with `Approve:{Model}` permission) when approval is requested.

**`ApprovalDecisionNotification`** — sent to the original requester when the record is approved or rejected.

Both use `NotificationDispatcher` and extend `BaseNotification` (database + mail + broadcast channels).

---

## Configuration

### Custom column name

```php
class Expense extends Model implements Approvable
{
    use RequiresApproval;

    public function getApprovalStatusColumn(): string
    {
        return 'status'; // Use 'status' instead of 'approval_status'
    }
}
```

### Custom permission name

```php
public function getApprovalPermission(): string
{
    return 'Manage:Expenses'; // Instead of 'Approve:Expense'
}
```

### Custom approver logic

```php
public function getApprovers(): Collection
{
    // Only the user's manager can approve
    return collect([$this->owner->manager])->filter();
}
```

---

## RBAC

The permission format follows AICL's `Action:Resource` convention:

```
Approve:Expense
Approve:PurchaseOrder
Approve:TimeOff
```

Assign to roles:

```php
$managerRole->givePermissionTo('Approve:Expense');
```

---

## Files

| File | Purpose |
|------|---------|
| `packages/aicl/src/Workflows/Traits/RequiresApproval.php` | Main trait |
| `packages/aicl/src/Workflows/Enums/ApprovalStatus.php` | Status enum (Draft, Pending, Approved, Rejected) |
| `packages/aicl/src/Workflows/Models/ApprovalLog.php` | Polymorphic audit log model |
| `packages/aicl/src/Workflows/Events/Approval*.php` | 4 event classes |
| `packages/aicl/src/Workflows/Exceptions/ApprovalException.php` | State transition errors |
| `packages/aicl/src/Workflows/Notifications/Approval*.php` | 2 notification classes |
| `packages/aicl/src/Contracts/Approvable.php` | Interface contract |
| `packages/aicl/database/migrations/..._create_approval_logs_table.php` | Migration |
| `packages/aicl/tests/Feature/Workflows/ApprovalWorkflowTest.php` | Tests (22 tests) |
