<?php

namespace Aicl\Tests\Feature\Workflows;

use Aicl\Contracts\Approvable;
use Aicl\Events\DomainEvent;
use Aicl\Models\DomainEventRecord;
use Aicl\Workflows\Events\ApprovalGranted;
use Aicl\Workflows\Events\ApprovalRejected;
use Aicl\Workflows\Events\ApprovalRequested;
use Aicl\Workflows\Events\ApprovalRevoked;
use Aicl\Workflows\Exceptions\ApprovalException;
use Aicl\Workflows\Models\ApprovalLog;
use Aicl\Workflows\Traits\RequiresApproval;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $approver;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test table for the approvable model
        if (! Schema::hasTable('test_approvables')) {
            Schema::create('test_approvables', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('approval_status')->default('draft')->index();
                $table->timestamps();
            });
        }

        // Create permission
        Permission::findOrCreate('Approve:TestApprovable', 'web');

        // Create users
        $this->requester = User::factory()->create();
        $this->approver = User::factory()->create();
        $this->approver->givePermissionTo('Approve:TestApprovable');
        $this->unauthorizedUser = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_approvables');

        parent::tearDown();
    }

    // -- Full Lifecycle Tests --

    public function test_full_approval_lifecycle(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class]);

        $model = TestApprovable::create(['name' => 'Test Item']);

        // Start as draft
        $this->assertFalse($model->isPendingApproval());
        $this->assertFalse($model->isApproved());

        // Request approval
        $model->requestApproval($this->requester, 'Please review');

        $this->assertTrue($model->fresh()->isPendingApproval());
        Event::assertDispatched(ApprovalRequested::class);

        // Approve
        $model->approve($this->approver, 'Looks good');

        $this->assertTrue($model->fresh()->isApproved());
        Event::assertDispatched(ApprovalGranted::class);

        // Verify audit trail
        $this->assertSame(2, $model->approvalLogs()->count());
    }

    public function test_rejection_and_resubmission_lifecycle(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalRejected::class, ApprovalGranted::class]);

        $model = TestApprovable::create(['name' => 'Test Item']);

        // Request → Reject
        $model->requestApproval($this->requester);
        $model->reject($this->approver, 'Needs more detail');

        $this->assertTrue($model->fresh()->isRejected());
        Event::assertDispatched(ApprovalRejected::class);

        // Re-submit → Approve
        $model->requestApproval($this->requester, 'Added more detail');
        $model->approve($this->approver);

        $this->assertTrue($model->fresh()->isApproved());

        // 4 log entries: request, reject, re-request, approve
        $this->assertSame(4, $model->approvalLogs()->count());
    }

    public function test_revoke_approval_lifecycle(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class, ApprovalRevoked::class]);

        $model = TestApprovable::create(['name' => 'Test Item']);

        $model->requestApproval($this->requester);
        $model->approve($this->approver);

        $this->assertTrue($model->fresh()->isApproved());

        // Revoke
        $model->revokeApproval($this->approver, 'New information found');

        $this->assertTrue($model->fresh()->isPendingApproval());
        Event::assertDispatched(ApprovalRevoked::class);

        $this->assertSame(3, $model->approvalLogs()->count());
    }

    // -- Status Check Tests --

    public function test_status_checks_on_fresh_model(): void
    {
        $model = TestApprovable::create(['name' => 'Fresh']);

        $this->assertFalse($model->isPendingApproval());
        $this->assertFalse($model->isApproved());
        $this->assertFalse($model->isRejected());
    }

    // -- RBAC Tests --

    public function test_unauthorized_user_cannot_approve(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);

        $this->expectException(AuthorizationException::class);

        $model->approve($this->unauthorizedUser);
    }

    public function test_unauthorized_user_cannot_reject(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);

        $this->expectException(AuthorizationException::class);

        $model->reject($this->unauthorizedUser, 'No permission');
    }

    public function test_unauthorized_user_cannot_revoke(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);

        $this->expectException(AuthorizationException::class);

        $model->revokeApproval($this->unauthorizedUser, 'No permission');
    }

    // -- Invalid State Transition Tests --

    public function test_cannot_request_approval_when_already_pending(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('already pending');

        $model->requestApproval($this->requester);
    }

    public function test_cannot_request_approval_when_already_approved(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('already approved');

        $model->requestApproval($this->requester);
    }

    public function test_cannot_approve_when_not_pending(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('not pending');

        $model->approve($this->approver);
    }

    public function test_cannot_reject_when_not_pending(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('not pending');

        $model->reject($this->approver, 'Cannot reject draft');
    }

    public function test_cannot_revoke_when_not_approved(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('not approved');

        $model->revokeApproval($this->approver, 'Cannot revoke pending');
    }

    // -- Approval Log Tests --

    public function test_approval_log_records_all_fields(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester, 'Please review this');

        $log = ApprovalLog::query()->latest()->first();

        $this->assertSame($this->requester->id, $log->actor_id);
        $this->assertSame('requested', $log->action);
        $this->assertSame('draft', $log->from_status);
        $this->assertSame('pending', $log->to_status);
        $this->assertSame('Please review this', $log->comment);
        $this->assertSame(TestApprovable::class, $log->approvable_type);
        $this->assertSame($model->id, $log->approvable_id);
    }

    public function test_approval_log_actor_relationship(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);

        $log = $model->approvalLogs()->first();

        $this->assertInstanceOf(User::class, $log->actor);
        $this->assertSame($this->requester->id, $log->actor->id);
    }

    // -- Scope Tests --

    public function test_pending_approval_scope(): void
    {
        $draft = TestApprovable::create(['name' => 'Draft']);
        $pending = TestApprovable::create(['name' => 'Pending']);
        $pending->requestApproval($this->requester);

        $results = TestApprovable::pendingApproval()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Pending', $results->first()->name);
    }

    public function test_approved_scope(): void
    {
        $pending = TestApprovable::create(['name' => 'Pending']);
        $pending->requestApproval($this->requester);

        $approved = TestApprovable::create(['name' => 'Approved']);
        $approved->requestApproval($this->requester);
        $approved->approve($this->approver);

        $results = TestApprovable::approved()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Approved', $results->first()->name);
    }

    public function test_rejected_scope(): void
    {
        $pending = TestApprovable::create(['name' => 'Pending']);
        $pending->requestApproval($this->requester);

        $rejected = TestApprovable::create(['name' => 'Rejected']);
        $rejected->requestApproval($this->requester);
        $rejected->reject($this->approver, 'Not good enough');

        $results = TestApprovable::rejected()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Rejected', $results->first()->name);
    }

    // -- Configuration Tests --

    public function test_get_approval_permission_format(): void
    {
        $model = new TestApprovable;

        $this->assertSame('Approve:TestApprovable', $model->getApprovalPermission());
    }

    public function test_get_approval_status_column_default(): void
    {
        $model = new TestApprovable;

        $this->assertSame('approval_status', $model->getApprovalStatusColumn());
    }

    // -- Events Tests --

    public function test_approval_requested_event_carries_correct_data(): void
    {
        Event::fake([ApprovalRequested::class]);

        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester, 'Please review');

        Event::assertDispatched(ApprovalRequested::class, function (ApprovalRequested $event) use ($model) {
            return $event->approvable->is($model)
                && $event->requester->is($this->requester)
                && $event->comment === 'Please review';
        });
    }

    public function test_approval_granted_event_carries_correct_data(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class]);

        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver, 'LGTM');

        Event::assertDispatched(ApprovalGranted::class, function (ApprovalGranted $event) use ($model) {
            return $event->approvable->is($model)
                && $event->approver->is($this->approver)
                && $event->comment === 'LGTM';
        });
    }

    public function test_approval_rejected_event_carries_correct_data(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalRejected::class]);

        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);
        $model->reject($this->approver, 'Insufficient detail');

        Event::assertDispatched(ApprovalRejected::class, function (ApprovalRejected $event) use ($model) {
            return $event->approvable->is($model)
                && $event->rejector->is($this->approver)
                && $event->reason === 'Insufficient detail';
        });
    }

    // -- DomainEvent Persistence Tests --

    public function test_approval_events_extend_domain_event(): void
    {
        $this->assertInstanceOf(DomainEvent::class, new ApprovalRequested(new TestApprovable, $this->requester));
        $this->assertInstanceOf(DomainEvent::class, new ApprovalGranted(new TestApprovable, $this->approver));
        $this->assertInstanceOf(DomainEvent::class, new ApprovalRejected(new TestApprovable, $this->approver, 'reason'));
        $this->assertInstanceOf(DomainEvent::class, new ApprovalRevoked(new TestApprovable, $this->approver, 'reason'));
    }

    public function test_approval_requested_persists_to_domain_events(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester, 'Please review');

        $record = DomainEventRecord::ofType('approval.requested')->first();

        $this->assertNotNull($record);
        $this->assertSame('approval.requested', $record->event_type);
        $this->assertSame('user', $record->actor_type);
        $this->assertSame($this->requester->id, $record->actor_id);
        $this->assertSame(TestApprovable::class, $record->entity_type);
        $this->assertSame((string) $model->id, $record->entity_id);
        $this->assertSame('requested', $record->payload['action']);
        $this->assertSame('Please review', $record->payload['comment']);
    }

    public function test_approval_granted_persists_to_domain_events(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver, 'LGTM');

        $record = DomainEventRecord::ofType('approval.granted')->first();

        $this->assertNotNull($record);
        $this->assertSame('user', $record->actor_type);
        $this->assertSame($this->approver->id, $record->actor_id);
        $this->assertSame('granted', $record->payload['action']);
        $this->assertSame('LGTM', $record->payload['comment']);
    }

    public function test_approval_rejected_persists_to_domain_events(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);
        $model->reject($this->approver, 'Not ready');

        $record = DomainEventRecord::ofType('approval.rejected')->first();

        $this->assertNotNull($record);
        $this->assertSame($this->approver->id, $record->actor_id);
        $this->assertSame('rejected', $record->payload['action']);
        $this->assertSame('Not ready', $record->payload['reason']);
    }

    public function test_approval_revoked_persists_to_domain_events(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);
        $model->revokeApproval($this->approver, 'Policy change');

        $record = DomainEventRecord::ofType('approval.revoked')->first();

        $this->assertNotNull($record);
        $this->assertSame($this->approver->id, $record->actor_id);
        $this->assertSame('revoked', $record->payload['action']);
        $this->assertSame('Policy change', $record->payload['reason']);
    }

    public function test_domain_event_record_of_type_approval_wildcard(): void
    {
        $model = TestApprovable::create(['name' => 'Test']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);
        $model->revokeApproval($this->approver, 'Changed mind');

        $records = DomainEventRecord::ofType('approval.*')->get();

        $this->assertSame(3, $records->count());
        $types = $records->pluck('event_type')->sort()->values()->all();
        $this->assertSame(['approval.granted', 'approval.requested', 'approval.revoked'], $types);
    }
}

/**
 * Test model for approval workflow testing.
 */
class TestApprovable extends Model implements Approvable
{
    use RequiresApproval;

    protected $table = 'test_approvables';

    protected $guarded = [];
}
