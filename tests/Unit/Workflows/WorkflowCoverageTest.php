<?php

namespace Aicl\Tests\Unit\Workflows;

use Aicl\Contracts\Approvable;
use Aicl\Events\DomainEvent;
use Aicl\Events\Enums\ActorType;
use Aicl\Notifications\BaseNotification;
use Aicl\Workflows\Enums\ApprovalStatus;
use Aicl\Workflows\Events\ApprovalGranted;
use Aicl\Workflows\Events\ApprovalRejected;
use Aicl\Workflows\Events\ApprovalRequested;
use Aicl\Workflows\Events\ApprovalRevoked;
use Aicl\Workflows\Exceptions\ApprovalException;
use Aicl\Workflows\Models\ApprovalLog;
use Aicl\Workflows\Notifications\ApprovalDecisionNotification;
use Aicl\Workflows\Notifications\ApprovalRequestedNotification;
use Aicl\Workflows\Traits\RequiresApproval;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WorkflowCoverageTest extends TestCase
{
    // =====================================================================
    // ApprovalStatus Enum — cases
    // =====================================================================

    public function test_approval_status_has_draft_case(): void
    {
        $this->assertSame('draft', ApprovalStatus::Draft->value);
    }

    public function test_approval_status_has_pending_case(): void
    {
        $this->assertSame('pending', ApprovalStatus::Pending->value);
    }

    public function test_approval_status_has_approved_case(): void
    {
        $this->assertSame('approved', ApprovalStatus::Approved->value);
    }

    public function test_approval_status_has_rejected_case(): void
    {
        $this->assertSame('rejected', ApprovalStatus::Rejected->value);
    }

    public function test_approval_status_has_four_cases(): void
    {
        $cases = ApprovalStatus::cases();

        $this->assertCount(4, $cases);
    }

    #[DataProvider('approvalStatusProvider')]
    public function test_approval_status_label_returns_non_empty_string(ApprovalStatus $status): void
    {
        $label = $status->label();

        $this->assertNotEmpty($label);
    }

    #[DataProvider('approvalStatusProvider')]
    public function test_approval_status_color_returns_non_empty_string(ApprovalStatus $status): void
    {
        $color = $status->color();

        $this->assertNotEmpty($color);
    }

    #[DataProvider('approvalStatusProvider')]
    public function test_approval_status_icon_returns_heroicon_string(ApprovalStatus $status): void
    {
        $icon = $status->icon();

        $this->assertStringStartsWith('heroicon-o-', $icon);
    }

    /**
     * @return array<string, array{ApprovalStatus}>
     */
    public static function approvalStatusProvider(): array
    {
        return [
            'Draft' => [ApprovalStatus::Draft],
            'Pending' => [ApprovalStatus::Pending],
            'Approved' => [ApprovalStatus::Approved],
            'Rejected' => [ApprovalStatus::Rejected],
        ];
    }

    public function test_approval_status_specific_labels(): void
    {
        $this->assertSame('Draft', ApprovalStatus::Draft->label());
        $this->assertSame('Pending Approval', ApprovalStatus::Pending->label());
        $this->assertSame('Approved', ApprovalStatus::Approved->label());
        $this->assertSame('Rejected', ApprovalStatus::Rejected->label());
    }

    public function test_approval_status_specific_colors(): void
    {
        $this->assertSame('gray', ApprovalStatus::Draft->color());
        $this->assertSame('warning', ApprovalStatus::Pending->color());
        $this->assertSame('success', ApprovalStatus::Approved->color());
        $this->assertSame('danger', ApprovalStatus::Rejected->color());
    }

    public function test_approval_status_specific_icons(): void
    {
        $this->assertSame('heroicon-o-pencil-square', ApprovalStatus::Draft->icon());
        $this->assertSame('heroicon-o-clock', ApprovalStatus::Pending->icon());
        $this->assertSame('heroicon-o-check-circle', ApprovalStatus::Approved->icon());
        $this->assertSame('heroicon-o-x-circle', ApprovalStatus::Rejected->icon());
    }

    public function test_approval_status_can_be_created_from_string(): void
    {
        $status = ApprovalStatus::from('draft');

        $this->assertSame(ApprovalStatus::Draft, $status);
    }

    public function test_approval_status_try_from_returns_null_for_invalid(): void
    {
        $status = ApprovalStatus::tryFrom('nonexistent');

        $this->assertNotSame(ApprovalStatus::Draft, $status);
    }

    // =====================================================================
    // ApprovalException — exception class structure
    // =====================================================================

    public function test_approval_exception_extends_runtime_exception(): void
    {
        $this->assertTrue((new \ReflectionClass(ApprovalException::class))->isSubclassOf(RuntimeException::class));
    }

    public function test_approval_exception_already_pending_contains_message(): void
    {
        $model = $this->createMockModel();

        $exception = ApprovalException::alreadyPending($model);

        $this->assertStringContainsString('already pending', $exception->getMessage());
    }

    public function test_approval_exception_already_approved_contains_message(): void
    {
        $model = $this->createMockModel();

        $exception = ApprovalException::alreadyApproved($model);

        $this->assertStringContainsString('already approved', $exception->getMessage());
    }

    public function test_approval_exception_not_pending_contains_message(): void
    {
        $model = $this->createMockModel();

        $exception = ApprovalException::notPending($model);

        $this->assertStringContainsString('not pending', $exception->getMessage());
    }

    public function test_approval_exception_not_approved_contains_message(): void
    {
        $model = $this->createMockModel();

        $exception = ApprovalException::notApproved($model);

        $this->assertStringContainsString('not approved', $exception->getMessage());
    }

    public function test_approval_exception_static_methods_return_instances(): void
    {
        $model = $this->createMockModel();

        $this->assertInstanceOf(ApprovalException::class, ApprovalException::alreadyPending($model));
        $this->assertInstanceOf(ApprovalException::class, ApprovalException::alreadyApproved($model));
        $this->assertInstanceOf(ApprovalException::class, ApprovalException::notPending($model));
        $this->assertInstanceOf(ApprovalException::class, ApprovalException::notApproved($model));
    }

    public function test_approval_exception_can_be_thrown(): void
    {
        $this->expectException(ApprovalException::class);
        $this->expectExceptionMessage('already pending');

        throw ApprovalException::alreadyPending($this->createMockModel());
    }

    // =====================================================================
    // ApprovalLog Model — structure
    // =====================================================================

    public function test_approval_log_extends_model(): void
    {
        $this->assertTrue((new \ReflectionClass(ApprovalLog::class))->isSubclassOf(Model::class));
    }

    public function test_approval_log_has_correct_table(): void
    {
        $log = new ApprovalLog;

        $this->assertSame('approval_logs', $log->getTable());
    }

    public function test_approval_log_has_correct_fillable_attributes(): void
    {
        $log = new ApprovalLog;
        $fillable = $log->getFillable();

        $this->assertContains('approvable_type', $fillable);
        $this->assertContains('approvable_id', $fillable);
        $this->assertContains('actor_id', $fillable);
        $this->assertContains('action', $fillable);
        $this->assertContains('from_status', $fillable);
        $this->assertContains('to_status', $fillable);
        $this->assertContains('comment', $fillable);
    }

    public function test_approval_log_fillable_has_seven_fields(): void
    {
        $log = new ApprovalLog;

        $this->assertCount(7, $log->getFillable());
    }

    public function test_approval_log_has_approvable_morph_to_relationship(): void
    {
        $log = new ApprovalLog;

        $this->assertInstanceOf(MorphTo::class, $log->approvable());
    }

    public function test_approval_log_has_actor_belongs_to_relationship(): void
    {
        $log = new ApprovalLog;

        $this->assertInstanceOf(BelongsTo::class, $log->actor());
    }

    // =====================================================================
    // Approval Events — extend DomainEvent
    // =====================================================================

    #[DataProvider('approvalEventProvider')]
    public function test_approval_event_extends_domain_event(string $eventClass): void
    {
        $this->assertTrue(
            is_subclass_of($eventClass, DomainEvent::class),
            "{$eventClass} should extend DomainEvent"
        );
    }

    #[DataProvider('approvalEventProvider')]
    public function test_approval_event_has_static_event_type(string $eventClass): void
    {
        $this->assertNotEmpty($eventClass::$eventType, "{$eventClass} should have a non-empty \$eventType");
    }

    #[DataProvider('approvalEventProvider')]
    public function test_approval_event_class_exists(string $eventClass): void
    {
        $this->assertTrue(class_exists($eventClass));
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function approvalEventProvider(): array
    {
        return [
            'ApprovalRequested' => [ApprovalRequested::class],
            'ApprovalGranted' => [ApprovalGranted::class],
            'ApprovalRejected' => [ApprovalRejected::class],
            'ApprovalRevoked' => [ApprovalRevoked::class],
        ];
    }

    public function test_approval_requested_event_type(): void
    {
        $this->assertSame('approval.requested', ApprovalRequested::$eventType);
    }

    public function test_approval_granted_event_type(): void
    {
        $this->assertSame('approval.granted', ApprovalGranted::$eventType);
    }

    public function test_approval_rejected_event_type(): void
    {
        $this->assertSame('approval.rejected', ApprovalRejected::$eventType);
    }

    public function test_approval_revoked_event_type(): void
    {
        $this->assertSame('approval.revoked', ApprovalRevoked::$eventType);
    }

    public function test_approval_requested_event_is_instantiable(): void
    {
        $user = $this->createMockUser(1, 'Requester');
        $model = $this->createMockModel();

        $event = new ApprovalRequested($model, $user, 'Please review');

        $this->assertSame($model, $event->approvable);
        $this->assertSame($user, $event->requester);
        $this->assertSame('Please review', $event->comment);
    }

    public function test_approval_requested_event_comment_defaults_to_null(): void
    {
        $user = $this->createMockUser(1, 'Requester');
        $model = $this->createMockModel();

        $event = new ApprovalRequested($model, $user);

        $this->assertNull($event->comment);
    }

    public function test_approval_requested_to_payload_returns_array(): void
    {
        $user = $this->createMockUser(1, 'Requester');
        $model = $this->createMockModel();

        $event = new ApprovalRequested($model, $user, 'Review it');
        $payload = $event->toPayload();

        $this->assertSame('requested', $payload['action']);
        $this->assertSame('Review it', $payload['comment']);
    }

    public function test_approval_requested_to_payload_filters_null_comment(): void
    {
        $user = $this->createMockUser(1, 'Requester');
        $model = $this->createMockModel();

        $event = new ApprovalRequested($model, $user, null);
        $payload = $event->toPayload();

        $this->assertSame('requested', $payload['action']);
        $this->assertArrayNotHasKey('comment', $payload);
    }

    public function test_approval_granted_event_is_instantiable(): void
    {
        $user = $this->createMockUser(2, 'Approver');
        $model = $this->createMockModel();

        $event = new ApprovalGranted($model, $user, 'Looks good');

        $this->assertSame($model, $event->approvable);
        $this->assertSame($user, $event->approver);
        $this->assertSame('Looks good', $event->comment);
    }

    public function test_approval_granted_to_payload_returns_array(): void
    {
        $user = $this->createMockUser(2, 'Approver');
        $model = $this->createMockModel();

        $event = new ApprovalGranted($model, $user, 'LGTM');
        $payload = $event->toPayload();

        $this->assertSame('granted', $payload['action']);
        $this->assertSame('LGTM', $payload['comment']);
    }

    public function test_approval_rejected_event_is_instantiable(): void
    {
        $user = $this->createMockUser(3, 'Rejector');
        $model = $this->createMockModel();

        $event = new ApprovalRejected($model, $user, 'Needs more work');

        $this->assertSame($model, $event->approvable);
        $this->assertSame($user, $event->rejector);
        $this->assertSame('Needs more work', $event->reason);
    }

    public function test_approval_rejected_to_payload_returns_array(): void
    {
        $user = $this->createMockUser(3, 'Rejector');
        $model = $this->createMockModel();

        $event = new ApprovalRejected($model, $user, 'Insufficient detail');
        $payload = $event->toPayload();

        $this->assertSame('rejected', $payload['action']);
        $this->assertSame('Insufficient detail', $payload['reason']);
    }

    public function test_approval_revoked_event_is_instantiable(): void
    {
        $user = $this->createMockUser(4, 'Revoker');
        $model = $this->createMockModel();

        $event = new ApprovalRevoked($model, $user, 'Policy change');

        $this->assertSame($model, $event->approvable);
        $this->assertSame($user, $event->revoker);
        $this->assertSame('Policy change', $event->reason);
    }

    public function test_approval_revoked_to_payload_returns_array(): void
    {
        $user = $this->createMockUser(4, 'Revoker');
        $model = $this->createMockModel();

        $event = new ApprovalRevoked($model, $user, 'Changed mind');
        $payload = $event->toPayload();

        $this->assertSame('revoked', $payload['action']);
        $this->assertSame('Changed mind', $payload['reason']);
    }

    // =====================================================================
    // Approval events — actor type
    // =====================================================================

    public function test_approval_requested_actor_type_is_user(): void
    {
        $user = $this->createMockUser(10, 'Test');
        $event = new ApprovalRequested($this->createMockModel(), $user);

        $this->assertSame(ActorType::User, $event->getActorType());
    }

    public function test_approval_granted_actor_type_is_user(): void
    {
        $user = $this->createMockUser(11, 'Approver');
        $event = new ApprovalGranted($this->createMockModel(), $user);

        $this->assertSame(ActorType::User, $event->getActorType());
    }

    public function test_approval_rejected_actor_id_matches_rejector(): void
    {
        $user = $this->createMockUser(12, 'Rejector');
        $event = new ApprovalRejected($this->createMockModel(), $user, 'reason');

        $this->assertSame(12, $event->getActorId());
    }

    public function test_approval_revoked_actor_id_matches_revoker(): void
    {
        $user = $this->createMockUser(13, 'Revoker');
        $event = new ApprovalRevoked($this->createMockModel(), $user, 'reason');

        $this->assertSame(13, $event->getActorId());
    }

    public function test_approval_requested_has_event_id(): void
    {
        $user = $this->createMockUser(1, 'Test');
        $event = new ApprovalRequested($this->createMockModel(), $user);

        $this->assertNotEmpty($event->eventId);
    }

    public function test_approval_requested_has_occurred_at(): void
    {
        $user = $this->createMockUser(1, 'Test');
        $event = new ApprovalRequested($this->createMockModel(), $user);

        $this->assertInstanceOf(Carbon::class, $event->occurredAt);
    }

    // =====================================================================
    // Approvable Contract
    // =====================================================================

    public function test_approvable_contract_is_interface(): void
    {
        $reflection = new \ReflectionClass(Approvable::class);

        $this->assertTrue($reflection->isInterface());
    }

    public function test_approvable_contract_defines_request_approval(): void
    {
        $reflection = new \ReflectionClass(Approvable::class);

        $this->assertTrue($reflection->hasMethod('requestApproval'));
    }

    public function test_approvable_contract_defines_approve(): void
    {
        $reflection = new \ReflectionClass(Approvable::class);

        $this->assertTrue($reflection->hasMethod('approve'));
    }

    public function test_approvable_contract_defines_reject(): void
    {
        $reflection = new \ReflectionClass(Approvable::class);

        $this->assertTrue($reflection->hasMethod('reject'));
    }

    public function test_approvable_contract_defines_status_checks(): void
    {
        $reflection = new \ReflectionClass(Approvable::class);

        $this->assertTrue($reflection->hasMethod('isPendingApproval'));
        $this->assertTrue($reflection->hasMethod('isApproved'));
        $this->assertTrue($reflection->hasMethod('isRejected'));
    }

    public function test_approvable_contract_defines_approval_logs(): void
    {
        $reflection = new \ReflectionClass(Approvable::class);

        $this->assertTrue($reflection->hasMethod('approvalLogs'));
    }

    public function test_approvable_contract_has_seven_methods(): void
    {
        $reflection = new \ReflectionClass(Approvable::class);

        $this->assertCount(7, $reflection->getMethods());
    }

    // =====================================================================
    // RequiresApproval Trait — method existence
    // =====================================================================

    public function test_requires_approval_trait_exists(): void
    {
        $this->assertTrue(trait_exists(RequiresApproval::class));
    }

    public function test_requires_approval_trait_has_expected_methods(): void
    {
        $reflection = new \ReflectionClass(RequiresApproval::class);

        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $expectedMethods = [
            'initializeRequiresApproval',
            'requestApproval',
            'approve',
            'reject',
            'revokeApproval',
            'isPendingApproval',
            'isApproved',
            'isRejected',
            'approvalLogs',
            'scopePendingApproval',
            'scopeApproved',
            'scopeRejected',
            'getApprovalStatusColumn',
            'getApprovalPermission',
            'getApprovers',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertContains($method, $methods, "RequiresApproval should have {$method} method");
        }
    }

    // =====================================================================
    // Workflow Notifications — structure
    // =====================================================================

    public function test_approval_requested_notification_extends_base_notification(): void
    {
        $this->assertTrue((new \ReflectionClass(ApprovalRequestedNotification::class))->isSubclassOf(BaseNotification::class));
    }

    public function test_approval_decision_notification_extends_base_notification(): void
    {
        $this->assertTrue((new \ReflectionClass(ApprovalDecisionNotification::class))->isSubclassOf(BaseNotification::class));
    }

    public function test_approval_requested_notification_is_instantiable(): void
    {
        $user = $this->createMockUser(1, 'Requester');
        $model = $this->createNamedMockModel('Test Item');

        $notification = new ApprovalRequestedNotification($model, $user, 'Review please');

        $this->assertSame($model, $notification->approvable);
        $this->assertSame($user, $notification->requester);
        $this->assertSame('Review please', $notification->comment);
    }

    public function test_approval_requested_notification_to_database_returns_expected_keys(): void
    {
        $user = $this->createMockUser(1, 'Requester');
        $model = $this->createNamedMockModel('Test Item');

        $notification = new ApprovalRequestedNotification($model, $user);
        $data = $notification->toDatabase($user);

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('icon', $data);
        $this->assertArrayHasKey('color', $data);
        $this->assertArrayHasKey('action_url', $data);
        $this->assertArrayHasKey('action_text', $data);
    }

    public function test_approval_requested_notification_body_includes_requester_name(): void
    {
        $user = $this->createMockUser(1, 'John Doe');
        $model = $this->createNamedMockModel('Test Entity');

        $notification = new ApprovalRequestedNotification($model, $user);
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('John Doe', $data['body']);
    }

    public function test_approval_requested_notification_body_includes_comment_when_provided(): void
    {
        $user = $this->createMockUser(1, 'Requester');
        $model = $this->createNamedMockModel('Entity');

        $notification = new ApprovalRequestedNotification($model, $user, 'Urgent review needed');
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('Urgent review needed', $data['body']);
    }

    public function test_approval_requested_notification_icon_is_clock(): void
    {
        $user = $this->createMockUser(1, 'Test');
        $notification = new ApprovalRequestedNotification($this->createMockModel(), $user);

        $this->assertSame('heroicon-o-clock', $notification->getIcon());
    }

    public function test_approval_requested_notification_color_is_warning(): void
    {
        $user = $this->createMockUser(1, 'Test');
        $notification = new ApprovalRequestedNotification($this->createMockModel(), $user);

        $this->assertSame('warning', $notification->getColor());
    }

    public function test_approval_decision_notification_is_instantiable_with_approved(): void
    {
        $user = $this->createMockUser(1, 'Decider');
        $model = $this->createMockModel();

        $notification = new ApprovalDecisionNotification(
            $model,
            $user,
            ApprovalStatus::Approved,
            'Great work'
        );

        $this->assertSame($model, $notification->approvable);
        $this->assertSame($user, $notification->decider);
        $this->assertSame(ApprovalStatus::Approved, $notification->decision);
        $this->assertSame('Great work', $notification->comment);
    }

    public function test_approval_decision_notification_to_database_returns_expected_keys(): void
    {
        $user = $this->createMockUser(1, 'Decider');
        $model = $this->createNamedMockModel('Test');

        $notification = new ApprovalDecisionNotification(
            $model,
            $user,
            ApprovalStatus::Approved,
        );
        $data = $notification->toDatabase($user);

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('icon', $data);
        $this->assertArrayHasKey('color', $data);
        $this->assertArrayHasKey('action_url', $data);
        $this->assertArrayHasKey('action_text', $data);
    }

    public function test_approval_decision_notification_approved_icon_is_check_circle(): void
    {
        $user = $this->createMockUser(1, 'Decider');
        $notification = new ApprovalDecisionNotification($this->createMockModel(), $user, ApprovalStatus::Approved);

        $this->assertSame('heroicon-o-check-circle', $notification->getIcon());
    }

    public function test_approval_decision_notification_rejected_icon_is_x_circle(): void
    {
        $user = $this->createMockUser(1, 'Decider');
        $notification = new ApprovalDecisionNotification($this->createMockModel(), $user, ApprovalStatus::Rejected);

        $this->assertSame('heroicon-o-x-circle', $notification->getIcon());
    }

    public function test_approval_decision_notification_approved_color_is_success(): void
    {
        $user = $this->createMockUser(1, 'Decider');
        $notification = new ApprovalDecisionNotification($this->createMockModel(), $user, ApprovalStatus::Approved);

        $this->assertSame('success', $notification->getColor());
    }

    public function test_approval_decision_notification_rejected_color_is_danger(): void
    {
        $user = $this->createMockUser(1, 'Decider');
        $notification = new ApprovalDecisionNotification($this->createMockModel(), $user, ApprovalStatus::Rejected);

        $this->assertSame('danger', $notification->getColor());
    }

    public function test_approval_decision_notification_body_includes_decider_name(): void
    {
        $user = $this->createMockUser(1, 'Jane Smith');
        $model = $this->createNamedMockModel('Report');

        $notification = new ApprovalDecisionNotification(
            $model,
            $user,
            ApprovalStatus::Approved,
            'Looks excellent'
        );
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('Jane Smith', $data['body']);
        $this->assertStringContainsString('Looks excellent', $data['body']);
    }

    public function test_approval_decision_notification_body_uses_approved_action_text(): void
    {
        $user = $this->createMockUser(1, 'Decider');
        $model = $this->createNamedMockModel('Item');

        $notification = new ApprovalDecisionNotification($model, $user, ApprovalStatus::Approved);
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('approved', $data['body']);
    }

    public function test_approval_decision_notification_body_uses_rejected_action_text(): void
    {
        $user = $this->createMockUser(1, 'Decider');
        $model = $this->createNamedMockModel('Item');

        $notification = new ApprovalDecisionNotification($model, $user, ApprovalStatus::Rejected);
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('rejected', $data['body']);
    }

    public function test_approval_decision_notification_title_includes_status_label(): void
    {
        $user = $this->createMockUser(1, 'Decider');
        $model = $this->createNamedMockModel('Item');

        $notification = new ApprovalDecisionNotification($model, $user, ApprovalStatus::Approved);
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('Approved', $data['title']);
    }

    // =====================================================================
    // Helpers — mock models to avoid database
    // =====================================================================

    private function createMockModel(): Model
    {
        return new class extends Model
        {
            protected $table = 'test_models';

            protected $guarded = [];
        };
    }

    private function createNamedMockModel(string $name): Model
    {
        $model = new class extends Model
        {
            protected $table = 'test_models';

            public string $name = '';

            protected $guarded = [];
        };
        $model->name = $name;

        return $model;
    }

    private function createMockUser(int $id, string $name): User
    {
        $user = new User;
        $user->id = $id;
        $user->name = $name;
        $user->email = strtolower(str_replace(' ', '.', $name)).'@example.com';
        $user->exists = true;

        return $user;
    }
}
