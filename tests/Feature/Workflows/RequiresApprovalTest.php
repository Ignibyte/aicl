<?php

namespace Aicl\Tests\Feature\Workflows;

use Aicl\Contracts\Approvable;
use Aicl\Services\NotificationDispatcher;
use Aicl\Workflows\Enums\ApprovalStatus;
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
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RequiresApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $approver;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('test_approvables')) {
            Schema::create('test_approvables', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->string('approval_status')->default('draft');
                $table->timestamps();
            });
        }

        // Mock NotificationDispatcher to prevent side effects (gotcha #6)
        $this->app->instance(
            NotificationDispatcher::class,
            Mockery::mock(NotificationDispatcher::class)->shouldIgnoreMissing()
        );

        Permission::findOrCreate('Approve:TestRequiresApprovalModel', 'web');

        $this->requester = User::factory()->create();
        $this->approver = User::factory()->create();
        $this->approver->givePermissionTo('Approve:TestRequiresApprovalModel');
        $this->unauthorizedUser = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_approvables');

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // State Machine
    // ---------------------------------------------------------------

    public function test_new_model_starts_in_draft_status(): void
    {
        $model = TestRequiresApprovalModel::create(['name' => 'Draft Item']);

        $this->assertSame(ApprovalStatus::Draft, $model->fresh()->approval_status);
    }

    public function test_request_approval_transitions_from_draft_to_pending(): void
    {
        Event::fake([ApprovalRequested::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);

        $this->assertSame(ApprovalStatus::Pending, $model->fresh()->approval_status);
    }

    public function test_approve_transitions_from_pending_to_approved(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);

        $this->assertSame(ApprovalStatus::Approved, $model->fresh()->approval_status);
    }

    public function test_reject_transitions_from_pending_to_rejected(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalRejected::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->reject($this->approver, 'Not ready');

        $this->assertSame(ApprovalStatus::Rejected, $model->fresh()->approval_status);
    }

    public function test_revoke_transitions_from_approved_to_pending(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class, ApprovalRevoked::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);
        $model->revokeApproval($this->approver, 'Needs re-review');

        $this->assertSame(ApprovalStatus::Pending, $model->fresh()->approval_status);
    }

    // ---------------------------------------------------------------
    // Guard Rails
    // ---------------------------------------------------------------

    public function test_request_approval_throws_when_already_pending(): void
    {
        Event::fake([ApprovalRequested::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('already pending');

        $model->requestApproval($this->requester);
    }

    public function test_request_approval_throws_when_already_approved(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('already approved');

        $model->requestApproval($this->requester);
    }

    public function test_approve_throws_when_not_pending(): void
    {
        $model = TestRequiresApprovalModel::create(['name' => 'Item']);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('not pending');

        $model->approve($this->approver);
    }

    public function test_reject_throws_when_not_pending(): void
    {
        $model = TestRequiresApprovalModel::create(['name' => 'Item']);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('not pending');

        $model->reject($this->approver, 'Cannot reject draft');
    }

    public function test_revoke_throws_when_not_approved(): void
    {
        Event::fake([ApprovalRequested::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);

        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('not approved');

        $model->revokeApproval($this->approver, 'Cannot revoke pending');
    }

    // ---------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------

    public function test_approve_requires_permission(): void
    {
        Event::fake([ApprovalRequested::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Approve:TestRequiresApprovalModel');

        $model->approve($this->unauthorizedUser);
    }

    public function test_reject_requires_permission(): void
    {
        Event::fake([ApprovalRequested::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Approve:TestRequiresApprovalModel');

        $model->reject($this->unauthorizedUser, 'No permission');
    }

    public function test_revoke_requires_permission(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Approve:TestRequiresApprovalModel');

        $model->revokeApproval($this->unauthorizedUser, 'No permission');
    }

    // ---------------------------------------------------------------
    // Status Checks
    // ---------------------------------------------------------------

    public function test_is_pending_approval_returns_true_when_pending(): void
    {
        Event::fake([ApprovalRequested::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);

        $this->assertTrue($model->isPendingApproval());
        $this->assertFalse($model->isApproved());
        $this->assertFalse($model->isRejected());
    }

    public function test_is_approved_returns_true_when_approved(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);

        $this->assertTrue($model->isApproved());
        $this->assertFalse($model->isPendingApproval());
        $this->assertFalse($model->isRejected());
    }

    public function test_is_rejected_returns_true_when_rejected(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalRejected::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->reject($this->approver, 'Rejected');

        $this->assertTrue($model->isRejected());
        $this->assertFalse($model->isPendingApproval());
        $this->assertFalse($model->isApproved());
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function test_pending_approval_scope_filters_correctly(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class, ApprovalRejected::class]);

        $draft = TestRequiresApprovalModel::create(['name' => 'Draft']);
        $pending = TestRequiresApprovalModel::create(['name' => 'Pending']);
        $pending->requestApproval($this->requester);

        $approved = TestRequiresApprovalModel::create(['name' => 'Approved']);
        $approved->requestApproval($this->requester);
        $approved->approve($this->approver);

        $results = TestRequiresApprovalModel::pendingApproval()->pluck('name')->all();

        $this->assertContains('Pending', $results);
        $this->assertNotContains('Draft', $results);
        $this->assertNotContains('Approved', $results);
    }

    public function test_approved_scope_filters_correctly(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class]);

        $draft = TestRequiresApprovalModel::create(['name' => 'Draft']);
        $pending = TestRequiresApprovalModel::create(['name' => 'Pending']);
        $pending->requestApproval($this->requester);

        $approved = TestRequiresApprovalModel::create(['name' => 'Approved']);
        $approved->requestApproval($this->requester);
        $approved->approve($this->approver);

        $results = TestRequiresApprovalModel::approved()->pluck('name')->all();

        $this->assertContains('Approved', $results);
        $this->assertNotContains('Draft', $results);
        $this->assertNotContains('Pending', $results);
    }

    public function test_rejected_scope_filters_correctly(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalRejected::class]);

        $draft = TestRequiresApprovalModel::create(['name' => 'Draft']);
        $pending = TestRequiresApprovalModel::create(['name' => 'Pending']);
        $pending->requestApproval($this->requester);

        $rejected = TestRequiresApprovalModel::create(['name' => 'Rejected']);
        $rejected->requestApproval($this->requester);
        $rejected->reject($this->approver, 'Bad');

        $results = TestRequiresApprovalModel::rejected()->pluck('name')->all();

        $this->assertContains('Rejected', $results);
        $this->assertNotContains('Draft', $results);
        $this->assertNotContains('Pending', $results);
    }

    // ---------------------------------------------------------------
    // Approval Logs
    // ---------------------------------------------------------------

    public function test_request_approval_creates_log_entry(): void
    {
        Event::fake([ApprovalRequested::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester, 'Please review');

        $log = $model->approvalLogs()->first();

        $this->assertNotNull($log);
        $this->assertInstanceOf(ApprovalLog::class, $log);
        $this->assertSame($this->requester->id, $log->actor_id);
        $this->assertSame('requested', $log->action);
        $this->assertSame('draft', $log->from_status);
        $this->assertSame('pending', $log->to_status);
        $this->assertSame('Please review', $log->comment);
    }

    public function test_approve_creates_log_entry(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver, 'Looks good');

        $log = $model->approvalLogs()->where('action', 'approved')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->approver->id, $log->actor_id);
        $this->assertSame('approved', $log->action);
        $this->assertSame('pending', $log->from_status);
        $this->assertSame('approved', $log->to_status);
        $this->assertSame('Looks good', $log->comment);
    }

    public function test_reject_creates_log_entry_with_reason(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalRejected::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->reject($this->approver, 'Insufficient detail');

        $log = $model->approvalLogs()->where('action', 'rejected')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->approver->id, $log->actor_id);
        $this->assertSame('rejected', $log->action);
        $this->assertSame('pending', $log->from_status);
        $this->assertSame('rejected', $log->to_status);
        $this->assertSame('Insufficient detail', $log->comment);
    }

    // ---------------------------------------------------------------
    // Events
    // ---------------------------------------------------------------

    public function test_request_approval_dispatches_event(): void
    {
        Event::fake([ApprovalRequested::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester, 'Please review');

        Event::assertDispatched(ApprovalRequested::class, function (ApprovalRequested $event) use ($model) {
            return $event->approvable->is($model)
                && $event->requester->is($this->requester)
                && $event->comment === 'Please review';
        });
    }

    public function test_approve_dispatches_event(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver, 'LGTM');

        Event::assertDispatched(ApprovalGranted::class, function (ApprovalGranted $event) use ($model) {
            return $event->approvable->is($model)
                && $event->approver->is($this->approver)
                && $event->comment === 'LGTM';
        });
    }

    public function test_reject_dispatches_event(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalRejected::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->reject($this->approver, 'Not good enough');

        Event::assertDispatched(ApprovalRejected::class, function (ApprovalRejected $event) use ($model) {
            return $event->approvable->is($model)
                && $event->rejector->is($this->approver)
                && $event->reason === 'Not good enough';
        });
    }

    public function test_revoke_dispatches_event(): void
    {
        Event::fake([ApprovalRequested::class, ApprovalGranted::class, ApprovalRevoked::class]);

        $model = TestRequiresApprovalModel::create(['name' => 'Item']);
        $model->requestApproval($this->requester);
        $model->approve($this->approver);
        $model->revokeApproval($this->approver, 'Policy change');

        Event::assertDispatched(ApprovalRevoked::class, function (ApprovalRevoked $event) use ($model) {
            return $event->approvable->is($model)
                && $event->revoker->is($this->approver)
                && $event->reason === 'Policy change';
        });
    }

    // ---------------------------------------------------------------
    // Configuration
    // ---------------------------------------------------------------

    public function test_default_approval_status_column(): void
    {
        $model = new TestRequiresApprovalModel;

        $this->assertSame('approval_status', $model->getApprovalStatusColumn());
    }

    public function test_default_approval_permission(): void
    {
        $model = new TestRequiresApprovalModel;

        $this->assertSame('Approve:TestRequiresApprovalModel', $model->getApprovalPermission());
    }
}

/**
 * Concrete test model that implements Approvable and uses RequiresApproval trait.
 */
class TestRequiresApprovalModel extends Model implements Approvable
{
    use RequiresApproval;

    protected $table = 'test_approvables';

    protected $guarded = [];
}
