<?php

namespace Aicl\Tests\Unit;

use Aicl\AI\AiAssistantRequest;
use Aicl\Contracts\Approvable;
use Aicl\Contracts\Auditable;
use Aicl\Contracts\DeclaresBaseSchema;
use Aicl\Contracts\EmbeddingDriver;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Contracts\Searchable;
use Aicl\Contracts\Stateful;
use Aicl\Contracts\Taggable;
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
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InfrastructureCoverageTest extends TestCase
{
    use RefreshDatabase;

    // =====================================================================
    // ApprovalStatus Enum
    // =====================================================================

    public function test_approval_status_has_four_cases(): void
    {
        $cases = ApprovalStatus::cases();

        $this->assertCount(4, $cases);
    }

    public function test_approval_status_draft_value(): void
    {
        $this->assertSame('draft', ApprovalStatus::Draft->value);
    }

    public function test_approval_status_pending_value(): void
    {
        $this->assertSame('pending', ApprovalStatus::Pending->value);
    }

    public function test_approval_status_approved_value(): void
    {
        $this->assertSame('approved', ApprovalStatus::Approved->value);
    }

    public function test_approval_status_rejected_value(): void
    {
        $this->assertSame('rejected', ApprovalStatus::Rejected->value);
    }

    public function test_approval_status_draft_label(): void
    {
        $this->assertSame('Draft', ApprovalStatus::Draft->label());
    }

    public function test_approval_status_pending_label(): void
    {
        $this->assertSame('Pending Approval', ApprovalStatus::Pending->label());
    }

    public function test_approval_status_approved_label(): void
    {
        $this->assertSame('Approved', ApprovalStatus::Approved->label());
    }

    public function test_approval_status_rejected_label(): void
    {
        $this->assertSame('Rejected', ApprovalStatus::Rejected->label());
    }

    public function test_approval_status_draft_color(): void
    {
        $this->assertSame('gray', ApprovalStatus::Draft->color());
    }

    public function test_approval_status_pending_color(): void
    {
        $this->assertSame('warning', ApprovalStatus::Pending->color());
    }

    public function test_approval_status_approved_color(): void
    {
        $this->assertSame('success', ApprovalStatus::Approved->color());
    }

    public function test_approval_status_rejected_color(): void
    {
        $this->assertSame('danger', ApprovalStatus::Rejected->color());
    }

    public function test_approval_status_draft_icon(): void
    {
        $this->assertSame('heroicon-o-pencil-square', ApprovalStatus::Draft->icon());
    }

    public function test_approval_status_pending_icon(): void
    {
        $this->assertSame('heroicon-o-clock', ApprovalStatus::Pending->icon());
    }

    public function test_approval_status_approved_icon(): void
    {
        $this->assertSame('heroicon-o-check-circle', ApprovalStatus::Approved->icon());
    }

    public function test_approval_status_rejected_icon(): void
    {
        $this->assertSame('heroicon-o-x-circle', ApprovalStatus::Rejected->icon());
    }

    public function test_approval_status_from_valid_string(): void
    {
        $this->assertSame(ApprovalStatus::Draft, ApprovalStatus::from('draft'));
        $this->assertSame(ApprovalStatus::Pending, ApprovalStatus::from('pending'));
        $this->assertSame(ApprovalStatus::Approved, ApprovalStatus::from('approved'));
        $this->assertSame(ApprovalStatus::Rejected, ApprovalStatus::from('rejected'));
    }

    public function test_approval_status_try_from_invalid_returns_null(): void
    {
        $this->assertNull(ApprovalStatus::tryFrom('nonexistent'));
    }

    // =====================================================================
    // ApprovalException
    // =====================================================================

    public function test_approval_exception_extends_runtime_exception(): void
    {
        $this->assertTrue(is_subclass_of(ApprovalException::class, RuntimeException::class));
    }

    public function test_approval_exception_already_pending(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $exception = ApprovalException::alreadyPending($model);

        $this->assertInstanceOf(ApprovalException::class, $exception);
        $this->assertStringContainsString('already pending approval', $exception->getMessage());
    }

    public function test_approval_exception_already_approved(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $exception = ApprovalException::alreadyApproved($model);

        $this->assertInstanceOf(ApprovalException::class, $exception);
        $this->assertStringContainsString('already approved', $exception->getMessage());
    }

    public function test_approval_exception_not_pending(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $exception = ApprovalException::notPending($model);

        $this->assertInstanceOf(ApprovalException::class, $exception);
        $this->assertStringContainsString('not pending approval', $exception->getMessage());
    }

    public function test_approval_exception_not_approved(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $exception = ApprovalException::notApproved($model);

        $this->assertInstanceOf(ApprovalException::class, $exception);
        $this->assertStringContainsString('not approved', $exception->getMessage());
    }

    public function test_approval_exception_message_includes_class_basename(): void
    {
        $user = new User;

        $exception = ApprovalException::alreadyPending($user);

        $this->assertStringContainsString('User', $exception->getMessage());
    }

    // =====================================================================
    // ApprovalLog Model
    // =====================================================================

    public function test_approval_log_table_name(): void
    {
        $log = new ApprovalLog;

        $this->assertSame('approval_logs', $log->getTable());
    }

    public function test_approval_log_fillable_attributes(): void
    {
        $log = new ApprovalLog;

        $expected = [
            'approvable_type',
            'approvable_id',
            'actor_id',
            'action',
            'from_status',
            'to_status',
            'comment',
        ];

        $this->assertSame($expected, $log->getFillable());
    }

    public function test_approval_log_approvable_returns_morph_to(): void
    {
        $log = new ApprovalLog;

        $this->assertInstanceOf(MorphTo::class, $log->approvable());
    }

    public function test_approval_log_actor_returns_belongs_to(): void
    {
        $log = new ApprovalLog;

        $this->assertInstanceOf(BelongsTo::class, $log->actor());
    }

    public function test_approval_log_actor_relationship_uses_actor_id(): void
    {
        $log = new ApprovalLog;

        $relation = $log->actor();

        $this->assertSame('actor_id', $relation->getForeignKeyName());
    }

    // =====================================================================
    // ApprovalDecisionNotification
    // =====================================================================

    public function test_approval_decision_notification_extends_base_notification(): void
    {
        $this->assertTrue(is_subclass_of(ApprovalDecisionNotification::class, BaseNotification::class));
    }

    public function test_approval_decision_notification_to_database_approved(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';

            public string $name = 'Test Entity';

            public function getKey(): mixed
            {
                return 42;
            }
        };

        $decider = User::factory()->create(['name' => 'Admin User']);

        $notification = new ApprovalDecisionNotification(
            approvable: $approvable,
            decider: $decider,
            decision: ApprovalStatus::Approved,
            comment: 'Looks good',
        );

        $data = $notification->toDatabase($decider);

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('icon', $data);
        $this->assertArrayHasKey('color', $data);
        $this->assertStringContainsString('Approved', $data['title']);
        $this->assertStringContainsString('approved', $data['body']);
        $this->assertStringContainsString('Admin User', $data['body']);
        $this->assertStringContainsString('Test Entity', $data['body']);
        $this->assertStringContainsString('Looks good', $data['body']);
    }

    public function test_approval_decision_notification_to_database_rejected(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';

            public string $title = 'My Document';

            public function getKey(): mixed
            {
                return 99;
            }
        };

        $decider = User::factory()->create(['name' => 'Reviewer']);

        $notification = new ApprovalDecisionNotification(
            approvable: $approvable,
            decider: $decider,
            decision: ApprovalStatus::Rejected,
            comment: 'Needs work',
        );

        $data = $notification->toDatabase($decider);

        $this->assertStringContainsString('Rejected', $data['title']);
        $this->assertStringContainsString('rejected', $data['body']);
        $this->assertStringContainsString('Needs work', $data['body']);
    }

    public function test_approval_decision_notification_to_database_without_comment(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';

            public string $name = 'Item';

            public function getKey(): mixed
            {
                return 1;
            }
        };

        $decider = User::factory()->create(['name' => 'Boss']);

        $notification = new ApprovalDecisionNotification(
            approvable: $approvable,
            decider: $decider,
            decision: ApprovalStatus::Approved,
        );

        $data = $notification->toDatabase($decider);

        $this->assertStringNotContainsString(':', $data['body']);
    }

    public function test_approval_decision_notification_icon_for_approved(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';
        };

        $decider = User::factory()->create();

        $notification = new ApprovalDecisionNotification(
            approvable: $approvable,
            decider: $decider,
            decision: ApprovalStatus::Approved,
        );

        $this->assertSame('heroicon-o-check-circle', $notification->getIcon());
    }

    public function test_approval_decision_notification_icon_for_rejected(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';
        };

        $decider = User::factory()->create();

        $notification = new ApprovalDecisionNotification(
            approvable: $approvable,
            decider: $decider,
            decision: ApprovalStatus::Rejected,
        );

        $this->assertSame('heroicon-o-x-circle', $notification->getIcon());
    }

    public function test_approval_decision_notification_color_for_approved(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';
        };

        $decider = User::factory()->create();

        $notification = new ApprovalDecisionNotification(
            approvable: $approvable,
            decider: $decider,
            decision: ApprovalStatus::Approved,
        );

        $this->assertSame('success', $notification->getColor());
    }

    public function test_approval_decision_notification_color_for_rejected(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';
        };

        $decider = User::factory()->create();

        $notification = new ApprovalDecisionNotification(
            approvable: $approvable,
            decider: $decider,
            decision: ApprovalStatus::Rejected,
        );

        $this->assertSame('danger', $notification->getColor());
    }

    public function test_approval_decision_notification_uses_key_fallback(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';

            public function getKey(): mixed
            {
                return 77;
            }
        };

        $decider = User::factory()->create(['name' => 'Decider']);

        $notification = new ApprovalDecisionNotification(
            approvable: $approvable,
            decider: $decider,
            decision: ApprovalStatus::Approved,
        );

        $data = $notification->toDatabase($decider);

        $this->assertStringContainsString('#77', $data['body']);
    }

    // =====================================================================
    // ApprovalRequestedNotification
    // =====================================================================

    public function test_approval_requested_notification_extends_base_notification(): void
    {
        $this->assertTrue(is_subclass_of(ApprovalRequestedNotification::class, BaseNotification::class));
    }

    public function test_approval_requested_notification_to_database(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';

            public string $name = 'Widget';

            public function getKey(): mixed
            {
                return 5;
            }
        };

        $requester = User::factory()->create(['name' => 'John Doe']);

        $notification = new ApprovalRequestedNotification(
            approvable: $approvable,
            requester: $requester,
            comment: 'Please review',
        );

        $data = $notification->toDatabase($requester);

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertStringContainsString('Approval Requested', $data['title']);
        $this->assertStringContainsString('John Doe', $data['body']);
        $this->assertStringContainsString('Widget', $data['body']);
        $this->assertStringContainsString('Please review', $data['body']);
        $this->assertSame('Review', $data['action_text']);
    }

    public function test_approval_requested_notification_to_database_without_comment(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';

            public string $title = 'Report';

            public function getKey(): mixed
            {
                return 3;
            }
        };

        $requester = User::factory()->create(['name' => 'Jane']);

        $notification = new ApprovalRequestedNotification(
            approvable: $approvable,
            requester: $requester,
        );

        $data = $notification->toDatabase($requester);

        $this->assertStringNotContainsString(':', $data['body']);
        $this->assertStringContainsString('Report', $data['body']);
    }

    public function test_approval_requested_notification_icon(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';
        };

        $requester = User::factory()->create();

        $notification = new ApprovalRequestedNotification(
            approvable: $approvable,
            requester: $requester,
        );

        $this->assertSame('heroicon-o-clock', $notification->getIcon());
    }

    public function test_approval_requested_notification_color(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';
        };

        $requester = User::factory()->create();

        $notification = new ApprovalRequestedNotification(
            approvable: $approvable,
            requester: $requester,
        );

        $this->assertSame('warning', $notification->getColor());
    }

    public function test_approval_requested_notification_uses_key_fallback(): void
    {
        $approvable = new class extends Model
        {
            protected $table = 'users';

            public function getKey(): mixed
            {
                return 42;
            }
        };

        $requester = User::factory()->create(['name' => 'Requester']);

        $notification = new ApprovalRequestedNotification(
            approvable: $approvable,
            requester: $requester,
        );

        $data = $notification->toDatabase($requester);

        $this->assertStringContainsString('#42', $data['body']);
    }

    // =====================================================================
    // Approval Events — toPayload()
    // =====================================================================

    public function test_approval_granted_to_payload(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $user = User::factory()->create();

        $event = new ApprovalGranted($model, $user, 'Nice work');

        $payload = $event->toPayload();

        $this->assertSame('granted', $payload['action']);
        $this->assertSame('Nice work', $payload['comment']);
    }

    public function test_approval_granted_to_payload_without_comment(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $user = User::factory()->create();

        $event = new ApprovalGranted($model, $user);

        $payload = $event->toPayload();

        $this->assertSame('granted', $payload['action']);
        $this->assertArrayNotHasKey('comment', $payload);
    }

    public function test_approval_rejected_to_payload(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $user = User::factory()->create();

        $event = new ApprovalRejected($model, $user, 'Not ready');

        $payload = $event->toPayload();

        $this->assertSame('rejected', $payload['action']);
        $this->assertSame('Not ready', $payload['reason']);
    }

    public function test_approval_requested_to_payload(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $user = User::factory()->create();

        $event = new ApprovalRequested($model, $user, 'Please review');

        $payload = $event->toPayload();

        $this->assertSame('requested', $payload['action']);
        $this->assertSame('Please review', $payload['comment']);
    }

    public function test_approval_requested_to_payload_without_comment(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $user = User::factory()->create();

        $event = new ApprovalRequested($model, $user);

        $payload = $event->toPayload();

        $this->assertSame('requested', $payload['action']);
        $this->assertArrayNotHasKey('comment', $payload);
    }

    public function test_approval_revoked_to_payload(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $user = User::factory()->create();

        $event = new ApprovalRevoked($model, $user, 'Policy change');

        $payload = $event->toPayload();

        $this->assertSame('revoked', $payload['action']);
        $this->assertSame('Policy change', $payload['reason']);
    }

    // =====================================================================
    // Approval Events — Event Type
    // =====================================================================

    public function test_approval_granted_event_type(): void
    {
        $this->assertSame('approval.granted', ApprovalGranted::$eventType);
    }

    public function test_approval_rejected_event_type(): void
    {
        $this->assertSame('approval.rejected', ApprovalRejected::$eventType);
    }

    public function test_approval_requested_event_type(): void
    {
        $this->assertSame('approval.requested', ApprovalRequested::$eventType);
    }

    public function test_approval_revoked_event_type(): void
    {
        $this->assertSame('approval.revoked', ApprovalRevoked::$eventType);
    }

    // =====================================================================
    // Approval Events — Extend DomainEvent
    // =====================================================================

    public function test_approval_granted_extends_domain_event(): void
    {
        $this->assertTrue(is_subclass_of(ApprovalGranted::class, DomainEvent::class));
    }

    public function test_approval_rejected_extends_domain_event(): void
    {
        $this->assertTrue(is_subclass_of(ApprovalRejected::class, DomainEvent::class));
    }

    public function test_approval_requested_extends_domain_event(): void
    {
        $this->assertTrue(is_subclass_of(ApprovalRequested::class, DomainEvent::class));
    }

    public function test_approval_revoked_extends_domain_event(): void
    {
        $this->assertTrue(is_subclass_of(ApprovalRevoked::class, DomainEvent::class));
    }

    // =====================================================================
    // Approval Events — Actor Type
    // =====================================================================

    public function test_approval_granted_sets_actor_type_to_user(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $user = User::factory()->create();

        $event = new ApprovalGranted($model, $user);

        $this->assertSame(ActorType::User, $event->getActorType());
        $this->assertSame($user->id, $event->getActorId());
    }

    public function test_approval_rejected_sets_actor_type_to_user(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';
        };

        $user = User::factory()->create();

        $event = new ApprovalRejected($model, $user, 'reason');

        $this->assertSame(ActorType::User, $event->getActorType());
        $this->assertSame($user->id, $event->getActorId());
    }

    // =====================================================================
    // Contracts — Interface Method Declarations
    // =====================================================================

    public function test_approvable_contract_declares_required_methods(): void
    {
        $reflection = new \ReflectionClass(Approvable::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('requestApproval'));
        $this->assertTrue($reflection->hasMethod('approve'));
        $this->assertTrue($reflection->hasMethod('reject'));
        $this->assertTrue($reflection->hasMethod('isPendingApproval'));
        $this->assertTrue($reflection->hasMethod('isApproved'));
        $this->assertTrue($reflection->hasMethod('isRejected'));
        $this->assertTrue($reflection->hasMethod('approvalLogs'));
    }

    public function test_auditable_contract_is_marker_interface(): void
    {
        $reflection = new \ReflectionClass(Auditable::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertEmpty($reflection->getMethods());
    }

    public function test_declares_base_schema_contract_declares_base_schema_method(): void
    {
        $reflection = new \ReflectionClass(DeclaresBaseSchema::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('baseSchema'));
        $this->assertTrue($reflection->getMethod('baseSchema')->isStatic());
    }

    public function test_embedding_driver_contract_declares_required_methods(): void
    {
        $reflection = new \ReflectionClass(EmbeddingDriver::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('embed'));
        $this->assertTrue($reflection->hasMethod('embedBatch'));
        $this->assertTrue($reflection->hasMethod('dimension'));
    }

    public function test_has_entity_lifecycle_is_marker_interface(): void
    {
        $reflection = new \ReflectionClass(HasEntityLifecycle::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertEmpty($reflection->getMethods());
    }

    public function test_searchable_contract_declares_to_searchable_array(): void
    {
        $reflection = new \ReflectionClass(Searchable::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('toSearchableArray'));
    }

    public function test_stateful_is_marker_interface(): void
    {
        $reflection = new \ReflectionClass(Stateful::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertEmpty($reflection->getMethods());
    }

    public function test_taggable_is_marker_interface(): void
    {
        $reflection = new \ReflectionClass(Taggable::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertEmpty($reflection->getMethods());
    }

    // =====================================================================
    // AiAssistantRequest
    // =====================================================================

    public function test_ai_assistant_request_extends_form_request(): void
    {
        $this->assertTrue(is_subclass_of(AiAssistantRequest::class, FormRequest::class));
    }

    public function test_ai_assistant_request_rules_contain_prompt(): void
    {
        $request = new AiAssistantRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('prompt', $rules);
        $this->assertContains('required', $rules['prompt']);
        $this->assertContains('string', $rules['prompt']);
    }

    public function test_ai_assistant_request_rules_contain_entity_type(): void
    {
        $request = new AiAssistantRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('entity_type', $rules);
        $this->assertContains('nullable', $rules['entity_type']);
        $this->assertContains('string', $rules['entity_type']);
    }

    public function test_ai_assistant_request_rules_contain_entity_id(): void
    {
        $request = new AiAssistantRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('entity_id', $rules);
        $this->assertContains('nullable', $rules['entity_id']);
        $this->assertContains('required_with:entity_type', $rules['entity_id']);
    }

    public function test_ai_assistant_request_rules_contain_system_prompt(): void
    {
        $request = new AiAssistantRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('system_prompt', $rules);
        $this->assertContains('nullable', $rules['system_prompt']);
        $this->assertContains('string', $rules['system_prompt']);
    }

    public function test_ai_assistant_request_prompt_max_length_from_config(): void
    {
        config(['aicl.ai.max_prompt_length' => 500]);

        $request = new AiAssistantRequest;
        $rules = $request->rules();

        $this->assertContains('max:500', $rules['prompt']);
    }

    public function test_ai_assistant_request_messages_returns_custom_messages(): void
    {
        $request = new AiAssistantRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('prompt.required', $messages);
        $this->assertArrayHasKey('prompt.max', $messages);
        $this->assertArrayHasKey('entity_id.required_with', $messages);
    }

    public function test_ai_assistant_request_authorize_returns_false_for_no_user(): void
    {
        $request = AiAssistantRequest::create('/test', 'POST');
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }

    public function test_ai_assistant_request_authorize_returns_true_for_admin(): void
    {
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $request = AiAssistantRequest::create('/test', 'POST');
        $request->setUserResolver(fn () => $admin);

        $this->assertTrue($request->authorize());
    }

    public function test_ai_assistant_request_authorize_returns_false_for_viewer(): void
    {
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $request = AiAssistantRequest::create('/test', 'POST');
        $request->setUserResolver(fn () => $viewer);

        $this->assertFalse($request->authorize());
    }
}
