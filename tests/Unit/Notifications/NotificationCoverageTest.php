<?php

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Models\FailureReport;
use Aicl\Models\RlmFailure;
use Aicl\Notifications\BaseNotification;
use Aicl\Notifications\Contracts\HasExternalChannels;
use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\Contracts\NotificationChannelResolver;
use Aicl\Notifications\Contracts\NotificationRecipientResolver;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Events\NotificationSending;
use Aicl\Notifications\FailurePromotionCandidateNotification;
use Aicl\Notifications\FailureRegressionNotification;
use Aicl\Notifications\FailureReportAssignedNotification;
use Aicl\Notifications\Jobs\RetryNotificationDelivery;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use Aicl\Notifications\RlmFailureAssignedNotification;
use Aicl\Notifications\RlmFailureStatusChangedNotification;
use Aicl\States\RlmFailure\Confirmed;
use Aicl\States\RlmFailure\Reported;
use Aicl\Workflows\Notifications\ApprovalDecisionNotification;
use Aicl\Workflows\Notifications\ApprovalRequestedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register stub routes that notifications reference for action_url generation.
        // Must use app('router') to ensure routes persist through the test lifecycle.
        $router = $this->app->make('router');
        $router->get('/admin/rlm-failures/{record}', fn () => null)
            ->name('filament.admin.resources.rlm_failures.view');
        $router->get('/admin/failure-reports/{record}', fn () => null)
            ->name('filament.admin.resources.failure-reports.view');

        // Refresh the route name lookup cache.
        $router->getRoutes()->refreshNameLookups();
    }

    // =====================================================================
    // Mock helpers — avoid database dependency
    // =====================================================================

    /**
     * Create a mock User object with the given properties.
     */
    private function createMockUser(int $id = 1, string $name = 'Test User', string $email = 'test@example.com'): User
    {
        $user = new User;
        $user->forceFill([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
        $user->exists = true;

        return $user;
    }

    /**
     * Create a mock RlmFailure object with the given properties.
     */
    private function createMockFailure(array $attributes = []): RlmFailure
    {
        $defaults = [
            'id' => 'mock-failure-uuid',
            'failure_code' => 'F-001',
            'title' => 'Test Failure',
            'report_count' => 3,
            'project_count' => 2,
        ];

        $failure = new RlmFailure;
        $failure->forceFill(array_merge($defaults, $attributes));
        $failure->exists = true;

        return $failure;
    }

    /**
     * Create a mock FailureReport object with the given properties.
     */
    private function createMockReport(array $attributes = []): FailureReport
    {
        $defaults = [
            'id' => 'mock-report-uuid',
            'entity_name' => 'Invoice',
            'project_hash' => 'abc123',
        ];

        $report = new FailureReport;
        $report->forceFill(array_merge($defaults, $attributes));
        $report->exists = true;

        return $report;
    }

    // =====================================================================
    // All concrete notification classes — extend BaseNotification
    // =====================================================================

    #[DataProvider('concreteNotificationClassProvider')]
    public function test_notification_extends_base_notification(string $notificationClass): void
    {
        $this->assertTrue(
            is_subclass_of($notificationClass, BaseNotification::class),
            "{$notificationClass} should extend BaseNotification"
        );
    }

    #[DataProvider('concreteNotificationClassProvider')]
    public function test_notification_extends_laravel_notification(string $notificationClass): void
    {
        $this->assertTrue(
            is_subclass_of($notificationClass, Notification::class),
            "{$notificationClass} should extend Notification"
        );
    }

    #[DataProvider('concreteNotificationClassProvider')]
    public function test_notification_implements_should_queue(string $notificationClass): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements($notificationClass)),
            "{$notificationClass} should implement ShouldQueue"
        );
    }

    #[DataProvider('concreteNotificationClassProvider')]
    public function test_notification_has_to_database_method(string $notificationClass): void
    {
        $reflection = new \ReflectionClass($notificationClass);

        $this->assertTrue($reflection->hasMethod('toDatabase'));
    }

    #[DataProvider('concreteNotificationClassProvider')]
    public function test_notification_has_get_icon_method(string $notificationClass): void
    {
        $reflection = new \ReflectionClass($notificationClass);

        $this->assertTrue($reflection->hasMethod('getIcon'));
    }

    #[DataProvider('concreteNotificationClassProvider')]
    public function test_notification_has_get_color_method(string $notificationClass): void
    {
        $reflection = new \ReflectionClass($notificationClass);

        $this->assertTrue($reflection->hasMethod('getColor'));
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function concreteNotificationClassProvider(): array
    {
        return [
            'RlmFailureAssigned' => [RlmFailureAssignedNotification::class],
            'RlmFailureStatusChanged' => [RlmFailureStatusChangedNotification::class],
            'FailureReportAssigned' => [FailureReportAssignedNotification::class],
            'FailurePromotionCandidate' => [FailurePromotionCandidateNotification::class],
            'FailureRegression' => [FailureRegressionNotification::class],
            'ApprovalRequested' => [ApprovalRequestedNotification::class],
            'ApprovalDecision' => [ApprovalDecisionNotification::class],
        ];
    }

    // =====================================================================
    // RlmFailureAssignedNotification — implements HasExternalChannels
    // =====================================================================

    public function test_rlm_failure_assigned_implements_has_external_channels(): void
    {
        $this->assertTrue(
            in_array(HasExternalChannels::class, class_implements(RlmFailureAssignedNotification::class))
        );
    }

    public function test_rlm_failure_assigned_has_external_channels_method(): void
    {
        $reflection = new \ReflectionClass(RlmFailureAssignedNotification::class);

        $this->assertTrue($reflection->hasMethod('externalChannels'));

        $method = $reflection->getMethod('externalChannels');
        $this->assertTrue($method->isPublic());
    }

    public function test_rlm_failure_assigned_to_database_data_structure(): void
    {
        $failure = $this->createMockFailure([
            'failure_code' => 'F-001',
            'title' => 'Test Failure',
        ]);
        $assignedBy = $this->createMockUser(2, 'Admin User');

        $notification = new RlmFailureAssignedNotification($failure, $assignedBy);
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertSame('Failure assigned to you', $data['title']);
        $this->assertStringContainsString('F-001', $data['body']);
        $this->assertStringContainsString('Test Failure', $data['body']);
        $this->assertStringContainsString('Admin User', $data['body']);
        $this->assertSame('heroicon-o-user-plus', $data['icon']);
        $this->assertSame('primary', $data['color']);
        $this->assertSame('View Failure', $data['action_text']);
    }

    public function test_rlm_failure_assigned_icon_and_color(): void
    {
        $failure = $this->createMockFailure();
        $assignedBy = $this->createMockUser();

        $notification = new RlmFailureAssignedNotification($failure, $assignedBy);

        $this->assertSame('heroicon-o-user-plus', $notification->getIcon());
        $this->assertSame('primary', $notification->getColor());
    }

    // =====================================================================
    // RlmFailureStatusChangedNotification — delegates color to new status
    // =====================================================================

    public function test_rlm_failure_status_changed_to_database_includes_status_labels(): void
    {
        $failure = $this->createMockFailure([
            'failure_code' => 'F-050',
            'title' => 'Status Bug',
        ]);
        $previousStatus = new Reported($failure);
        $newStatus = new Confirmed($failure);
        $changedBy = $this->createMockUser(3, 'Changer');

        $notification = new RlmFailureStatusChangedNotification(
            $failure,
            $previousStatus,
            $newStatus,
            $changedBy,
        );
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertSame('Failure status changed', $data['title']);
        $this->assertStringContainsString('F-050', $data['body']);
        $this->assertStringContainsString('Status Bug', $data['body']);
        $this->assertStringContainsString('Changer', $data['body']);
        $this->assertSame('heroicon-o-arrow-path', $data['icon']);
        $this->assertSame('View Failure', $data['action_text']);
    }

    public function test_rlm_failure_status_changed_body_without_changed_by(): void
    {
        $failure = $this->createMockFailure([
            'failure_code' => 'F-051',
            'title' => 'Auto Change',
        ]);
        $previousStatus = new Reported($failure);
        $newStatus = new Confirmed($failure);

        $notification = new RlmFailureStatusChangedNotification(
            $failure,
            $previousStatus,
            $newStatus,
        );
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('F-051', $data['body']);
        // Should not end with "by" since changedBy is null
        $this->assertStringNotContainsString(' by .', $data['body']);
    }

    public function test_rlm_failure_status_changed_icon_is_arrow_path(): void
    {
        $failure = $this->createMockFailure();
        $previousStatus = new Reported($failure);
        $newStatus = new Confirmed($failure);

        $notification = new RlmFailureStatusChangedNotification(
            $failure,
            $previousStatus,
            $newStatus,
        );

        $this->assertSame('heroicon-o-arrow-path', $notification->getIcon());
    }

    public function test_rlm_failure_status_changed_color_delegates_to_new_status(): void
    {
        $failure = $this->createMockFailure();
        $previousStatus = new Reported($failure);
        $newStatus = new Confirmed($failure);

        $notification = new RlmFailureStatusChangedNotification(
            $failure,
            $previousStatus,
            $newStatus,
        );

        // Confirmed state color is 'success'
        $this->assertSame('success', $notification->getColor());
    }

    // =====================================================================
    // FailureReportAssignedNotification — data structure
    // =====================================================================

    public function test_failure_report_assigned_to_database_data_structure(): void
    {
        $report = $this->createMockReport([
            'entity_name' => 'Invoice',
            'project_hash' => 'abc123',
        ]);
        $assignedBy = $this->createMockUser(4, 'Manager');

        $notification = new FailureReportAssignedNotification($report, $assignedBy);
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertSame('Failure report assigned to you', $data['title']);
        $this->assertStringContainsString('Invoice', $data['body']);
        $this->assertStringContainsString('abc123', $data['body']);
        $this->assertStringContainsString('Manager', $data['body']);
        $this->assertSame('heroicon-o-user-plus', $data['icon']);
        $this->assertSame('primary', $data['color']);
        $this->assertSame('View Report', $data['action_text']);
    }

    public function test_failure_report_assigned_icon_and_color(): void
    {
        $report = $this->createMockReport();
        $assignedBy = $this->createMockUser();

        $notification = new FailureReportAssignedNotification($report, $assignedBy);

        $this->assertSame('heroicon-o-user-plus', $notification->getIcon());
        $this->assertSame('primary', $notification->getColor());
    }

    // =====================================================================
    // FailurePromotionCandidateNotification — data structure
    // =====================================================================

    public function test_failure_promotion_candidate_to_database_title(): void
    {
        $failure = $this->createMockFailure();
        $notification = new FailurePromotionCandidateNotification($failure);
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertSame('Failure ready for promotion', $data['title']);
    }

    public function test_failure_promotion_candidate_action_text_is_review(): void
    {
        $failure = $this->createMockFailure();
        $notification = new FailurePromotionCandidateNotification($failure);
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertSame('Review for Promotion', $data['action_text']);
    }

    public function test_failure_promotion_candidate_icon_and_color(): void
    {
        $failure = $this->createMockFailure();
        $notification = new FailurePromotionCandidateNotification($failure);

        $this->assertSame('heroicon-o-arrow-trending-up', $notification->getIcon());
        $this->assertSame('success', $notification->getColor());
    }

    public function test_failure_promotion_candidate_to_database_body_includes_counts(): void
    {
        $failure = $this->createMockFailure([
            'failure_code' => 'F-100',
            'title' => 'Promotion Test',
            'report_count' => 5,
            'project_count' => 3,
        ]);
        $notification = new FailurePromotionCandidateNotification($failure);
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('F-100', $data['body']);
        $this->assertStringContainsString('Promotion Test', $data['body']);
        $this->assertStringContainsString('5', $data['body']);
        $this->assertStringContainsString('3', $data['body']);
    }

    // =====================================================================
    // FailureRegressionNotification — data structure
    // =====================================================================

    public function test_failure_regression_to_database_title(): void
    {
        $failure = $this->createMockFailure();
        $report = $this->createMockReport();
        $notification = new FailureRegressionNotification($failure, $report);
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertSame('Regression detected', $data['title']);
    }

    public function test_failure_regression_action_text_is_investigate(): void
    {
        $failure = $this->createMockFailure();
        $report = $this->createMockReport();
        $notification = new FailureRegressionNotification($failure, $report);
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertSame('Investigate Regression', $data['action_text']);
    }

    public function test_failure_regression_icon_and_color(): void
    {
        $failure = $this->createMockFailure();
        $report = $this->createMockReport();
        $notification = new FailureRegressionNotification($failure, $report);

        $this->assertSame('heroicon-o-exclamation-triangle', $notification->getIcon());
        $this->assertSame('danger', $notification->getColor());
    }

    public function test_failure_regression_body_references_entity_name(): void
    {
        $failure = $this->createMockFailure([
            'failure_code' => 'F-200',
            'title' => 'Regression Bug',
        ]);
        $report = $this->createMockReport([
            'entity_name' => 'Widget',
        ]);
        $notification = new FailureRegressionNotification($failure, $report);
        $user = $this->createMockUser();
        $data = $notification->toDatabase($user);

        $this->assertStringContainsString('F-200', $data['body']);
        $this->assertStringContainsString('Regression Bug', $data['body']);
        $this->assertStringContainsString('Widget', $data['body']);
    }

    // =====================================================================
    // BaseNotification — via() with onlyChannel restriction
    // =====================================================================

    public function test_base_notification_via_defaults_to_three_channels(): void
    {
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $channels = $notification->via($user);

        $this->assertSame(['database', 'mail', 'broadcast'], $channels);
    }

    public function test_base_notification_only_via_restricts_channels(): void
    {
        $notification = $this->createTestNotification();
        $notification->onlyVia('database');

        $user = $this->createMockUser();
        $this->assertSame(['database'], $notification->via($user));
    }

    public function test_base_notification_only_via_returns_self(): void
    {
        $notification = $this->createTestNotification();
        $result = $notification->onlyVia('mail');

        $this->assertSame($notification, $result);
    }

    public function test_base_notification_to_mail_returns_mail_message(): void
    {
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    public function test_base_notification_to_mail_uses_title_as_subject(): void
    {
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $mail = $notification->toMail($user);

        $this->assertSame('Test Notification', $mail->subject);
    }

    public function test_base_notification_to_broadcast_returns_broadcast_message(): void
    {
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $broadcast = $notification->toBroadcast($user);

        $this->assertInstanceOf(BroadcastMessage::class, $broadcast);
    }

    public function test_base_notification_default_icon_is_bell(): void
    {
        $notification = $this->createTestNotification();

        $this->assertSame('heroicon-o-bell', $notification->getIcon());
    }

    public function test_base_notification_default_color_is_primary(): void
    {
        $notification = $this->createTestNotification();

        $this->assertSame('primary', $notification->getColor());
    }

    public function test_base_notification_is_abstract(): void
    {
        $reflection = new \ReflectionClass(BaseNotification::class);

        $this->assertTrue($reflection->isAbstract());
    }

    public function test_base_notification_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(BaseNotification::class))
        );
    }

    // =====================================================================
    // NotificationSending Event — cancellation
    // =====================================================================

    public function test_notification_sending_starts_uncancelled(): void
    {
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $event = new NotificationSending($notification, $user);

        $this->assertFalse($event->cancelled);
    }

    public function test_notification_sending_can_be_cancelled(): void
    {
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $event = new NotificationSending($notification, $user);

        $event->cancel();

        $this->assertTrue($event->cancelled);
    }

    public function test_notification_sending_stores_notification_property(): void
    {
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $event = new NotificationSending($notification, $user);

        $this->assertSame($notification, $event->notification);
    }

    public function test_notification_sending_stores_notifiable_property(): void
    {
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $event = new NotificationSending($notification, $user);

        $this->assertSame($user, $event->notifiable);
    }

    public function test_notification_sending_stores_sender_property(): void
    {
        $sender = $this->createMockUser(2, 'Sender User');
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $event = new NotificationSending($notification, $user, $sender);

        $this->assertSame($sender, $event->sender);
    }

    public function test_notification_sending_sender_defaults_to_null(): void
    {
        $notification = $this->createTestNotification();
        $user = $this->createMockUser();
        $event = new NotificationSending($notification, $user);

        $this->assertNull($event->sender);
    }

    // =====================================================================
    // NotificationChannel Model — structure
    // =====================================================================

    public function test_notification_channel_extends_model(): void
    {
        $this->assertTrue(is_subclass_of(NotificationChannel::class, Model::class));
    }

    public function test_notification_channel_uses_uuids(): void
    {
        $traits = class_uses_recursive(NotificationChannel::class);

        $this->assertContains(HasUuids::class, $traits);
    }

    public function test_notification_channel_has_correct_table(): void
    {
        $channel = new NotificationChannel;

        $this->assertSame('notification_channels', $channel->getTable());
    }

    public function test_notification_channel_has_correct_fillable(): void
    {
        $channel = new NotificationChannel;
        $fillable = $channel->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('slug', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('config', $fillable);
        $this->assertContains('is_active', $fillable);
        $this->assertContains('message_templates', $fillable);
        $this->assertContains('rate_limit', $fillable);
    }

    public function test_notification_channel_get_template_returns_exact_match(): void
    {
        $channel = new NotificationChannel;
        $channel->message_templates = [
            'App\\Notifications\\TestNotification' => [
                'title' => 'Custom Title',
                'body' => 'Custom Body',
            ],
        ];

        $template = $channel->getTemplate('App\\Notifications\\TestNotification');

        $this->assertSame('Custom Title', $template['title']);
        $this->assertSame('Custom Body', $template['body']);
    }

    public function test_notification_channel_get_template_falls_back_to_default(): void
    {
        $channel = new NotificationChannel;
        $channel->message_templates = [
            '_default' => [
                'title' => 'Default Title',
                'body' => 'Default Body',
            ],
        ];

        $template = $channel->getTemplate('App\\Notifications\\NonExistent');

        $this->assertSame('Default Title', $template['title']);
    }

    public function test_notification_channel_get_template_returns_null_when_no_match(): void
    {
        $channel = new NotificationChannel;
        $channel->message_templates = [];

        $template = $channel->getTemplate('App\\Notifications\\NonExistent');

        $this->assertNull($template);
    }

    public function test_notification_channel_get_template_returns_null_when_templates_null(): void
    {
        $channel = new NotificationChannel;
        $channel->message_templates = null;

        $template = $channel->getTemplate('App\\Notifications\\Test');

        $this->assertNull($template);
    }

    public function test_notification_channel_has_active_scope(): void
    {
        $reflection = new \ReflectionClass(NotificationChannel::class);

        $this->assertTrue($reflection->hasMethod('scopeActive'));
    }

    // =====================================================================
    // NotificationDeliveryLog Model — structure
    // =====================================================================

    public function test_notification_delivery_log_extends_model(): void
    {
        $this->assertTrue(is_subclass_of(NotificationDeliveryLog::class, Model::class));
    }

    public function test_notification_delivery_log_uses_uuids(): void
    {
        $traits = class_uses_recursive(NotificationDeliveryLog::class);

        $this->assertContains(HasUuids::class, $traits);
    }

    public function test_notification_delivery_log_has_correct_table(): void
    {
        $log = new NotificationDeliveryLog;

        $this->assertSame('notification_delivery_logs', $log->getTable());
    }

    public function test_notification_delivery_log_timestamps_disabled(): void
    {
        $log = new NotificationDeliveryLog;

        $this->assertFalse($log->timestamps);
    }

    public function test_notification_delivery_log_has_correct_fillable(): void
    {
        $log = new NotificationDeliveryLog;
        $fillable = $log->getFillable();

        $this->assertContains('notification_log_id', $fillable);
        $this->assertContains('channel_id', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('attempt_count', $fillable);
        $this->assertContains('payload', $fillable);
        $this->assertContains('error_message', $fillable);
        $this->assertContains('sent_at', $fillable);
        $this->assertContains('delivered_at', $fillable);
        $this->assertContains('failed_at', $fillable);
        $this->assertContains('next_retry_at', $fillable);
    }

    public function test_notification_delivery_log_default_attempt_count_is_zero(): void
    {
        $log = new NotificationDeliveryLog;

        $this->assertSame(0, $log->attempt_count);
    }

    // =====================================================================
    // RetryNotificationDelivery Job — structure
    // =====================================================================

    public function test_retry_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(RetryNotificationDelivery::class))
        );
    }

    public function test_retry_job_can_be_instantiated_with_delivery_log_id(): void
    {
        $job = new RetryNotificationDelivery('test-uuid-123');

        $this->assertSame('test-uuid-123', $job->deliveryLogId);
    }

    public function test_retry_job_has_single_try(): void
    {
        $job = new RetryNotificationDelivery('test-uuid');

        $this->assertSame(1, $job->tries);
    }

    public function test_retry_job_uses_dispatchable(): void
    {
        $traits = class_uses_recursive(RetryNotificationDelivery::class);

        $this->assertContains(\Illuminate\Foundation\Bus\Dispatchable::class, $traits);
    }

    public function test_retry_job_uses_serializes_models(): void
    {
        $traits = class_uses_recursive(RetryNotificationDelivery::class);

        $this->assertContains(\Illuminate\Queue\SerializesModels::class, $traits);
    }

    // =====================================================================
    // DriverResult — value object structure
    // =====================================================================

    public function test_driver_result_success_factory(): void
    {
        $result = DriverResult::success('msg-123', ['key' => 'value']);

        $this->assertTrue($result->success);
        $this->assertNull($result->error);
        $this->assertSame('msg-123', $result->messageId);
        $this->assertSame(['key' => 'value'], $result->response);
    }

    public function test_driver_result_failure_factory(): void
    {
        $result = DriverResult::failure('Something went wrong', true);

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertSame('Something went wrong', $result->error);
    }

    public function test_driver_result_failure_non_retryable(): void
    {
        $result = DriverResult::failure('Bad request', false);

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        $this->assertSame('Bad request', $result->error);
    }

    public function test_driver_result_success_without_response(): void
    {
        $result = DriverResult::success();

        $this->assertTrue($result->success);
        $this->assertNull($result->messageId);
        $this->assertNull($result->response);
    }

    public function test_driver_result_success_with_message_id_only(): void
    {
        $result = DriverResult::success('msg-456');

        $this->assertTrue($result->success);
        $this->assertSame('msg-456', $result->messageId);
        $this->assertNull($result->response);
    }

    // =====================================================================
    // Enums — ChannelType and DeliveryStatus cases
    // =====================================================================

    public function test_channel_type_has_expected_cases(): void
    {
        $cases = ChannelType::cases();

        $this->assertNotEmpty($cases);

        $values = array_map(fn ($c) => $c->value, $cases);
        $this->assertContains('slack', $values);
        $this->assertContains('email', $values);
        $this->assertContains('webhook', $values);
    }

    public function test_delivery_status_has_expected_cases(): void
    {
        $cases = DeliveryStatus::cases();

        $this->assertNotEmpty($cases);

        $values = array_map(fn ($c) => $c->value, $cases);
        $this->assertContains('pending', $values);
        $this->assertContains('delivered', $values);
        $this->assertContains('failed', $values);
    }

    public function test_channel_type_is_backed_enum(): void
    {
        $reflection = new \ReflectionEnum(ChannelType::class);

        $this->assertTrue($reflection->isBacked());
    }

    public function test_delivery_status_is_backed_enum(): void
    {
        $reflection = new \ReflectionEnum(DeliveryStatus::class);

        $this->assertTrue($reflection->isBacked());
    }

    // =====================================================================
    // Contracts — interface definition checks
    // =====================================================================

    public function test_has_external_channels_is_interface(): void
    {
        $reflection = new \ReflectionClass(HasExternalChannels::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('externalChannels'));
    }

    public function test_notification_channel_driver_is_interface(): void
    {
        $reflection = new \ReflectionClass(NotificationChannelDriver::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('send'));
        $this->assertTrue($reflection->hasMethod('validateConfig'));
        $this->assertTrue($reflection->hasMethod('configSchema'));
    }

    public function test_notification_channel_resolver_is_interface(): void
    {
        $reflection = new \ReflectionClass(NotificationChannelResolver::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('resolve'));
    }

    public function test_notification_recipient_resolver_is_interface(): void
    {
        $reflection = new \ReflectionClass(NotificationRecipientResolver::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('resolve'));
    }

    // =====================================================================
    // Notification toMail integration — subject from toDatabase title
    // =====================================================================

    public function test_rlm_failure_assigned_to_mail_uses_title_as_subject(): void
    {
        $failure = $this->createMockFailure();
        $assignedBy = $this->createMockUser();
        $notification = new RlmFailureAssignedNotification($failure, $assignedBy);

        $user = $this->createMockUser();
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Failure assigned to you', $mail->subject);
    }

    public function test_failure_report_assigned_to_mail_uses_title_as_subject(): void
    {
        $report = $this->createMockReport();
        $assignedBy = $this->createMockUser();
        $notification = new FailureReportAssignedNotification($report, $assignedBy);

        $user = $this->createMockUser();
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Failure report assigned to you', $mail->subject);
    }

    public function test_failure_promotion_candidate_to_mail_uses_title_as_subject(): void
    {
        $failure = $this->createMockFailure();
        $notification = new FailurePromotionCandidateNotification($failure);

        $user = $this->createMockUser();
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Failure ready for promotion', $mail->subject);
    }

    public function test_failure_regression_to_mail_uses_title_as_subject(): void
    {
        $failure = $this->createMockFailure();
        $report = $this->createMockReport();
        $notification = new FailureRegressionNotification($failure, $report);

        $user = $this->createMockUser();
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Regression detected', $mail->subject);
    }

    // =====================================================================
    // Notification toBroadcast — returns BroadcastMessage with database data
    // =====================================================================

    public function test_rlm_failure_assigned_to_broadcast_returns_broadcast_message(): void
    {
        $failure = $this->createMockFailure();
        $assignedBy = $this->createMockUser();
        $notification = new RlmFailureAssignedNotification($failure, $assignedBy);

        $user = $this->createMockUser();
        $broadcast = $notification->toBroadcast($user);

        $this->assertInstanceOf(BroadcastMessage::class, $broadcast);
    }

    public function test_failure_promotion_candidate_to_broadcast_returns_broadcast_message(): void
    {
        $failure = $this->createMockFailure();
        $notification = new FailurePromotionCandidateNotification($failure);

        $user = $this->createMockUser();
        $broadcast = $notification->toBroadcast($user);

        $this->assertInstanceOf(BroadcastMessage::class, $broadcast);
    }

    public function test_failure_regression_to_broadcast_returns_broadcast_message(): void
    {
        $failure = $this->createMockFailure();
        $report = $this->createMockReport();
        $notification = new FailureRegressionNotification($failure, $report);

        $user = $this->createMockUser();
        $broadcast = $notification->toBroadcast($user);

        $this->assertInstanceOf(BroadcastMessage::class, $broadcast);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function createTestNotification(): BaseNotification
    {
        return new class extends BaseNotification
        {
            public function toDatabase(object $notifiable): array
            {
                return [
                    'title' => 'Test Notification',
                    'body' => 'Test body content',
                    'icon' => 'heroicon-o-bell',
                    'color' => 'primary',
                    'action_url' => 'https://example.com',
                    'action_text' => 'View',
                ];
            }
        };
    }
}
