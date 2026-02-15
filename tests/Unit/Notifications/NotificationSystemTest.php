<?php

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Models\FailureReport;
use Aicl\Models\NotificationLog;
use Aicl\Models\RlmFailure;
use Aicl\Notifications\BaseNotification;
use Aicl\Notifications\ChannelRateLimiter;
use Aicl\Notifications\Contracts\HasExternalChannels;
use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\Contracts\NotificationChannelResolver;
use Aicl\Notifications\Contracts\NotificationRecipientResolver;
use Aicl\Notifications\DriverRegistry;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Events\NotificationDispatched;
use Aicl\Notifications\Events\NotificationSending;
use Aicl\Notifications\FailurePromotionCandidateNotification;
use Aicl\Notifications\FailureRegressionNotification;
use Aicl\Notifications\FailureReportAssignedNotification;
use Aicl\Notifications\Jobs\RetryNotificationDelivery;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\RlmFailureAssignedNotification;
use Aicl\Notifications\RlmFailureStatusChangedNotification;
use Aicl\Services\NotificationDispatcher;
use Aicl\States\RlmFailure\Confirmed;
use Aicl\States\RlmFailure\Investigating;
use Aicl\States\RlmFailure\Reported;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $sender;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create();
        $this->sender = User::factory()->create();
    }

    // =====================================================================
    // NotificationDispatcher — send()
    // =====================================================================

    public function test_dispatcher_send_creates_notification_log(): void
    {
        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $log = $dispatcher->send($this->user, $notification);

        $this->assertInstanceOf(NotificationLog::class, $log);
        $this->assertDatabaseHas('notification_logs', [
            'id' => $log->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
        ]);
    }

    public function test_dispatcher_send_records_channels_in_log(): void
    {
        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $log = $dispatcher->send($this->user, $notification);

        $this->assertContains('database', $log->channels);
        $this->assertContains('mail', $log->channels);
        $this->assertContains('broadcast', $log->channels);
    }

    public function test_dispatcher_send_records_sender_when_provided(): void
    {
        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $log = $dispatcher->send($this->user, $notification, $this->sender);

        $this->assertSame(User::class, $log->sender_type);
        $this->assertSame($this->sender->getKey(), $log->sender_id);
    }

    public function test_dispatcher_send_records_null_sender_when_not_provided(): void
    {
        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $log = $dispatcher->send($this->user, $notification);

        $this->assertNull($log->sender_type);
        $this->assertNull($log->sender_id);
    }

    public function test_dispatcher_send_fires_notification_sending_event(): void
    {
        Event::fake([NotificationSending::class, NotificationDispatched::class]);

        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $dispatcher->send($this->user, $notification, $this->sender);

        Event::assertDispatched(NotificationSending::class, function (NotificationSending $event) {
            return $event->notifiable->is($this->user)
                && $event->sender->is($this->sender);
        });
    }

    public function test_dispatcher_send_fires_notification_dispatched_event(): void
    {
        Event::fake([NotificationSending::class, NotificationDispatched::class]);

        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $dispatcher->send($this->user, $notification);

        Event::assertDispatched(NotificationDispatched::class, function (NotificationDispatched $event) {
            return $event->notifiable->is($this->user)
                && $event->log instanceof NotificationLog;
        });
    }

    public function test_dispatcher_send_cancellation_via_event_creates_cancelled_log(): void
    {
        Event::listen(NotificationSending::class, function (NotificationSending $event): void {
            $event->cancel();
        });

        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $log = $dispatcher->send($this->user, $notification);

        $this->assertArrayHasKey('_cancelled', $log->channel_status);
        $this->assertSame('cancelled', $log->channel_status['_cancelled']);
        $this->assertEmpty($log->channels);
    }

    public function test_dispatcher_send_stores_to_database_data_in_log(): void
    {
        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification([
            'title' => 'Custom Title',
            'body' => 'Custom Body',
        ]);

        $log = $dispatcher->send($this->user, $notification);

        $this->assertSame('Custom Title', $log->data['title']);
        $this->assertSame('Custom Body', $log->data['body']);
    }

    // =====================================================================
    // NotificationDispatcher — sendToMany()
    // =====================================================================

    public function test_dispatcher_send_to_many_returns_collection_of_logs(): void
    {
        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $users = collect([
            $this->user,
            $this->sender,
        ]);

        $logs = $dispatcher->sendToMany($users, $notification);

        $this->assertInstanceOf(Collection::class, $logs);
        $this->assertCount(2, $logs);
        $logs->each(fn ($log) => $this->assertInstanceOf(NotificationLog::class, $log));
    }

    public function test_dispatcher_send_to_many_creates_separate_logs_per_notifiable(): void
    {
        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $users = collect([$this->user, $this->sender]);

        $dispatcher->sendToMany($users, $notification);

        $this->assertDatabaseCount('notification_logs', 2);
    }

    // =====================================================================
    // NotificationDispatcher — external channels
    // =====================================================================

    public function test_dispatcher_dispatches_to_external_channels_when_notification_implements_has_external(): void
    {
        Queue::fake();

        $channel = NotificationChannel::create([
            'name' => 'Slack Alerts',
            'slug' => 'slack-alerts',
            'type' => ChannelType::Slack,
            'config' => ['webhook_url' => 'https://hooks.slack.com/test'],
            'is_active' => true,
        ]);

        $dispatcher = $this->buildDispatcher();
        $notification = $this->createExternalNotification(collect([$channel]));

        $log = $dispatcher->send($this->user, $notification);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_log_id' => $log->id,
            'channel_id' => $channel->id,
            'status' => DeliveryStatus::Pending->value,
        ]);

        Queue::assertPushed(RetryNotificationDelivery::class);
    }

    public function test_dispatcher_uses_channel_resolver_when_bound(): void
    {
        Queue::fake();

        $channel = NotificationChannel::create([
            'name' => 'Resolver Channel',
            'slug' => 'resolver-channel',
            'type' => ChannelType::Webhook,
            'config' => ['url' => 'https://example.com/webhook'],
            'is_active' => true,
        ]);

        $resolver = new class($channel) implements NotificationChannelResolver
        {
            public function __construct(private NotificationChannel $channel) {}

            public function resolve(BaseNotification $notification, object $notifiable): Collection
            {
                return collect([$this->channel]);
            }
        };

        $dispatcher = new NotificationDispatcher(
            driverRegistry: new DriverRegistry($this->app),
            rateLimiter: new ChannelRateLimiter,
            channelResolver: $resolver,
        );

        $notification = $this->createTestNotification();

        $log = $dispatcher->send($this->user, $notification);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_log_id' => $log->id,
            'channel_id' => $channel->id,
        ]);
    }

    public function test_dispatcher_returns_empty_external_channels_when_no_resolver_and_no_interface(): void
    {
        $dispatcher = $this->buildDispatcher();
        $notification = $this->createTestNotification();

        $log = $dispatcher->send($this->user, $notification);

        $this->assertDatabaseCount('notification_delivery_logs', 0);
    }

    // =====================================================================
    // Concrete Notification — FailurePromotionCandidateNotification
    // =====================================================================

    public function test_failure_promotion_candidate_notification_extends_base(): void
    {
        $this->assertTrue(
            is_subclass_of(FailurePromotionCandidateNotification::class, BaseNotification::class)
        );
    }

    public function test_failure_promotion_candidate_to_database_contains_expected_keys(): void
    {
        $failure = RlmFailure::factory()->create();
        $notification = new FailurePromotionCandidateNotification($failure);

        $data = $notification->toDatabase($this->user);

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('icon', $data);
        $this->assertArrayHasKey('color', $data);
        $this->assertArrayHasKey('action_url', $data);
        $this->assertArrayHasKey('action_text', $data);
    }

    public function test_failure_promotion_candidate_has_success_color(): void
    {
        $failure = RlmFailure::factory()->create();
        $notification = new FailurePromotionCandidateNotification($failure);

        $this->assertSame('success', $notification->getColor());
    }

    public function test_failure_promotion_candidate_has_trending_up_icon(): void
    {
        $failure = RlmFailure::factory()->create();
        $notification = new FailurePromotionCandidateNotification($failure);

        $this->assertSame('heroicon-o-arrow-trending-up', $notification->getIcon());
    }

    public function test_failure_promotion_candidate_body_includes_failure_code(): void
    {
        $failure = RlmFailure::factory()->create([
            'failure_code' => 'F-999',
            'title' => 'Test Title',
        ]);
        $notification = new FailurePromotionCandidateNotification($failure);

        $data = $notification->toDatabase($this->user);

        $this->assertStringContainsString('F-999', $data['body']);
        $this->assertStringContainsString('Test Title', $data['body']);
    }

    public function test_failure_promotion_candidate_via_returns_default_channels(): void
    {
        $failure = RlmFailure::factory()->create();
        $notification = new FailurePromotionCandidateNotification($failure);

        $channels = $notification->via($this->user);

        $this->assertSame(['database', 'mail', 'broadcast'], $channels);
    }

    public function test_failure_promotion_candidate_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(FailurePromotionCandidateNotification::class, ShouldQueue::class)
        );
    }

    // =====================================================================
    // Concrete Notification — FailureRegressionNotification
    // =====================================================================

    public function test_failure_regression_notification_extends_base(): void
    {
        $this->assertTrue(
            is_subclass_of(FailureRegressionNotification::class, BaseNotification::class)
        );
    }

    public function test_failure_regression_to_database_contains_expected_keys(): void
    {
        $failure = RlmFailure::factory()->create();
        $report = FailureReport::factory()->create(['rlm_failure_id' => $failure->id]);
        $notification = new FailureRegressionNotification($failure, $report);

        $data = $notification->toDatabase($this->user);

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('icon', $data);
        $this->assertArrayHasKey('color', $data);
        $this->assertArrayHasKey('action_url', $data);
        $this->assertArrayHasKey('action_text', $data);
    }

    public function test_failure_regression_has_danger_color(): void
    {
        $failure = RlmFailure::factory()->create();
        $report = FailureReport::factory()->create(['rlm_failure_id' => $failure->id]);
        $notification = new FailureRegressionNotification($failure, $report);

        $this->assertSame('danger', $notification->getColor());
    }

    public function test_failure_regression_has_exclamation_triangle_icon(): void
    {
        $failure = RlmFailure::factory()->create();
        $report = FailureReport::factory()->create(['rlm_failure_id' => $failure->id]);
        $notification = new FailureRegressionNotification($failure, $report);

        $this->assertSame('heroicon-o-exclamation-triangle', $notification->getIcon());
    }

    public function test_failure_regression_body_includes_entity_name(): void
    {
        $failure = RlmFailure::factory()->create([
            'failure_code' => 'F-100',
            'title' => 'Regression Bug',
        ]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'entity_name' => 'Invoice',
        ]);
        $notification = new FailureRegressionNotification($failure, $report);

        $data = $notification->toDatabase($this->user);

        $this->assertStringContainsString('Invoice', $data['body']);
        $this->assertStringContainsString('F-100', $data['body']);
    }

    // =====================================================================
    // Concrete Notification — FailureReportAssignedNotification
    // =====================================================================

    public function test_failure_report_assigned_notification_extends_base(): void
    {
        $this->assertTrue(
            is_subclass_of(FailureReportAssignedNotification::class, BaseNotification::class)
        );
    }

    public function test_failure_report_assigned_has_user_plus_icon(): void
    {
        $report = FailureReport::factory()->create();
        $notification = new FailureReportAssignedNotification($report, $this->sender);

        $this->assertSame('heroicon-o-user-plus', $notification->getIcon());
    }

    public function test_failure_report_assigned_has_primary_color(): void
    {
        $report = FailureReport::factory()->create();
        $notification = new FailureReportAssignedNotification($report, $this->sender);

        $this->assertSame('primary', $notification->getColor());
    }

    public function test_failure_report_assigned_body_includes_assigned_by_name(): void
    {
        $report = FailureReport::factory()->create(['entity_name' => 'Contact']);
        $notification = new FailureReportAssignedNotification($report, $this->sender);

        $data = $notification->toDatabase($this->user);

        $this->assertStringContainsString($this->sender->name, $data['body']);
        $this->assertStringContainsString('Contact', $data['body']);
    }

    // =====================================================================
    // Concrete Notification — RlmFailureAssignedNotification
    // =====================================================================

    public function test_rlm_failure_assigned_notification_extends_base(): void
    {
        $this->assertTrue(
            is_subclass_of(RlmFailureAssignedNotification::class, BaseNotification::class)
        );
    }

    public function test_rlm_failure_assigned_implements_has_external_channels(): void
    {
        $this->assertTrue(
            in_array(HasExternalChannels::class, class_implements(RlmFailureAssignedNotification::class))
        );
    }

    public function test_rlm_failure_assigned_stores_failure_and_user_properties(): void
    {
        $failure = RlmFailure::factory()->create([
            'failure_code' => 'F-200',
            'title' => 'Missing Pattern',
        ]);
        $notification = new RlmFailureAssignedNotification($failure, $this->sender);

        $this->assertSame('F-200', $notification->rlm_failure->failure_code);
        $this->assertSame('Missing Pattern', $notification->rlm_failure->title);
        $this->assertTrue($notification->assignedBy->is($this->sender));
    }

    public function test_rlm_failure_assigned_external_channels_returns_active_channels(): void
    {
        NotificationChannel::create([
            'name' => 'Active Channel',
            'slug' => 'active-channel',
            'type' => ChannelType::Slack,
            'config' => ['webhook_url' => 'https://hooks.slack.com/test'],
            'is_active' => true,
        ]);

        NotificationChannel::create([
            'name' => 'Inactive Channel',
            'slug' => 'inactive-channel',
            'type' => ChannelType::Email,
            'config' => ['to' => ['admin@example.com']],
            'is_active' => false,
        ]);

        $failure = RlmFailure::factory()->create();
        $notification = new RlmFailureAssignedNotification($failure, $this->sender);

        $channels = $notification->externalChannels();

        $this->assertCount(1, $channels);
        $this->assertSame('active-channel', $channels->first()->slug);
    }

    // =====================================================================
    // Concrete Notification — RlmFailureStatusChangedNotification
    // =====================================================================

    public function test_rlm_failure_status_changed_notification_extends_base(): void
    {
        $this->assertTrue(
            is_subclass_of(RlmFailureStatusChangedNotification::class, BaseNotification::class)
        );
    }

    public function test_rlm_failure_status_changed_stores_constructor_properties(): void
    {
        $failure = RlmFailure::factory()->reported()->create([
            'failure_code' => 'F-300',
            'title' => 'Status Test',
        ]);
        $previousStatus = new Reported($failure);
        $newStatus = new Confirmed($failure);

        $notification = new RlmFailureStatusChangedNotification(
            $failure,
            $previousStatus,
            $newStatus,
            $this->sender,
        );

        $this->assertSame('F-300', $notification->rlm_failure->failure_code);
        $this->assertInstanceOf(Reported::class, $notification->previousStatus);
        $this->assertInstanceOf(Confirmed::class, $notification->newStatus);
        $this->assertTrue($notification->changedBy->is($this->sender));
    }

    public function test_rlm_failure_status_changed_allows_null_changed_by(): void
    {
        $failure = RlmFailure::factory()->reported()->create([
            'failure_code' => 'F-301',
            'title' => 'Auto Change',
        ]);
        $previousStatus = new Reported($failure);
        $newStatus = new Confirmed($failure);

        $notification = new RlmFailureStatusChangedNotification(
            $failure,
            $previousStatus,
            $newStatus,
        );

        $this->assertNull($notification->changedBy);
    }

    public function test_rlm_failure_status_changed_get_color_delegates_to_new_status(): void
    {
        $failure = RlmFailure::factory()->confirmed()->create();
        $previousStatus = new Confirmed($failure);
        $newStatus = new Investigating($failure);

        $notification = new RlmFailureStatusChangedNotification(
            $failure,
            $previousStatus,
            $newStatus,
        );

        $this->assertSame($newStatus->color(), $notification->getColor());
    }

    public function test_rlm_failure_status_changed_get_icon_returns_arrow_path(): void
    {
        $failure = RlmFailure::factory()->create();
        $previousStatus = new Reported($failure);
        $newStatus = new Confirmed($failure);

        $notification = new RlmFailureStatusChangedNotification(
            $failure,
            $previousStatus,
            $newStatus,
        );

        $this->assertSame('heroicon-o-arrow-path', $notification->getIcon());
    }

    // =====================================================================
    // NotificationLog model — scopes, relationships, read/unread
    // =====================================================================

    public function test_notification_log_for_user_scope(): void
    {
        NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->sender->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        $logs = NotificationLog::forUser($this->user)->get();

        $this->assertCount(1, $logs);
        $this->assertSame($this->user->id, $logs->first()->notifiable_id);
    }

    public function test_notification_log_of_type_scope(): void
    {
        NotificationLog::create([
            'type' => 'App\\Notifications\\SpecificType',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        NotificationLog::create([
            'type' => 'App\\Notifications\\OtherType',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        $logs = NotificationLog::ofType('App\\Notifications\\SpecificType')->get();

        $this->assertCount(1, $logs);
    }

    public function test_notification_log_unread_scope(): void
    {
        NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'read_at' => null,
        ]);

        NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'read_at' => now(),
        ]);

        $unread = NotificationLog::unread()->get();

        $this->assertCount(1, $unread);
        $this->assertNull($unread->first()->read_at);
    }

    public function test_notification_log_failed_scope(): void
    {
        NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database', 'mail'],
            'channel_status' => ['database' => 'sent', 'mail' => 'failed'],
        ]);

        NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        $failed = NotificationLog::failed()->get();

        $this->assertCount(1, $failed);
    }

    public function test_notification_log_mark_as_read(): void
    {
        $log = NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        $this->assertNull($log->read_at);

        $log->markAsRead();

        $log->refresh();
        $this->assertNotNull($log->read_at);
    }

    public function test_notification_log_mark_as_read_is_idempotent(): void
    {
        $log = NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        $log->markAsRead();
        $firstReadAt = $log->fresh()->read_at;

        $this->travel(5)->minutes();

        $log->markAsRead();
        $secondReadAt = $log->fresh()->read_at;

        $this->assertSame(
            $firstReadAt->format('Y-m-d H:i:s'),
            $secondReadAt->format('Y-m-d H:i:s'),
        );
    }

    public function test_notification_log_mark_as_unread(): void
    {
        $log = NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'read_at' => now(),
        ]);

        $log->markAsUnread();

        $log->refresh();
        $this->assertNull($log->read_at);
    }

    public function test_notification_log_type_label_attribute(): void
    {
        $log = NotificationLog::create([
            'type' => 'Aicl\\Notifications\\FailurePromotionCandidateNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        $label = $log->type_label;

        $this->assertIsString($label);
        $this->assertStringNotContainsString('Notification', $label);
        $this->assertNotSame('Unknown', $label);
    }

    public function test_notification_log_type_label_returns_unknown_for_empty_type(): void
    {
        $log = new NotificationLog;
        $log->type = null;

        $this->assertSame('Unknown', $log->type_label);
    }

    public function test_notification_log_notifiable_relationship(): void
    {
        $log = NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        $this->assertInstanceOf(User::class, $log->notifiable);
        $this->assertTrue($log->notifiable->is($this->user));
    }

    public function test_notification_log_sender_relationship(): void
    {
        $log = NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'sender_type' => User::class,
            'sender_id' => $this->sender->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        $this->assertInstanceOf(User::class, $log->sender);
        $this->assertTrue($log->sender->is($this->sender));
    }

    // =====================================================================
    // Contracts — implementation verification
    // =====================================================================

    public function test_notification_channel_driver_contract_defines_required_methods(): void
    {
        $reflection = new \ReflectionClass(NotificationChannelDriver::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('send'));
        $this->assertTrue($reflection->hasMethod('validateConfig'));
        $this->assertTrue($reflection->hasMethod('configSchema'));
    }

    public function test_notification_channel_resolver_contract_defines_resolve(): void
    {
        $reflection = new \ReflectionClass(NotificationChannelResolver::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('resolve'));
    }

    public function test_notification_recipient_resolver_contract_defines_resolve(): void
    {
        $reflection = new \ReflectionClass(NotificationRecipientResolver::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('resolve'));
    }

    public function test_has_external_channels_contract_defines_external_channels(): void
    {
        $reflection = new \ReflectionClass(HasExternalChannels::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('externalChannels'));
    }

    // =====================================================================
    // All drivers implement NotificationChannelDriver
    // =====================================================================

    #[DataProvider('driverClassProvider')]
    public function test_all_drivers_implement_notification_channel_driver(string $driverClass): void
    {
        $this->assertTrue(
            in_array(NotificationChannelDriver::class, class_implements($driverClass)),
            "{$driverClass} should implement NotificationChannelDriver"
        );
    }

    #[DataProvider('driverClassProvider')]
    public function test_all_drivers_have_send_method(string $driverClass): void
    {
        $reflection = new \ReflectionClass($driverClass);

        $this->assertTrue($reflection->hasMethod('send'));
        $sendMethod = $reflection->getMethod('send');
        $this->assertSame(DriverResult::class, $sendMethod->getReturnType()->getName());
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function driverClassProvider(): array
    {
        return [
            'EmailDriver' => [\Aicl\Notifications\Drivers\EmailDriver::class],
            'SlackDriver' => [\Aicl\Notifications\Drivers\SlackDriver::class],
            'TeamsDriver' => [\Aicl\Notifications\Drivers\TeamsDriver::class],
            'PagerDutyDriver' => [\Aicl\Notifications\Drivers\PagerDutyDriver::class],
            'WebhookDriver' => [\Aicl\Notifications\Drivers\WebhookDriver::class],
            'SmsDriver' => [\Aicl\Notifications\Drivers\SmsDriver::class],
        ];
    }

    // =====================================================================
    // RetryNotificationDelivery job structure
    // =====================================================================

    public function test_retry_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(RetryNotificationDelivery::class))
        );
    }

    public function test_retry_job_has_delivery_log_id_property(): void
    {
        $job = new RetryNotificationDelivery('test-uuid');

        $this->assertSame('test-uuid', $job->deliveryLogId);
    }

    public function test_retry_job_has_single_try(): void
    {
        $job = new RetryNotificationDelivery('test-uuid');

        $this->assertSame(1, $job->tries);
    }

    // =====================================================================
    // NotificationSending event
    // =====================================================================

    public function test_notification_sending_event_has_cancellable_flag(): void
    {
        $notification = $this->createTestNotification();
        $event = new NotificationSending($notification, $this->user);

        $this->assertFalse($event->cancelled);
        $event->cancel();
        $this->assertTrue($event->cancelled);
    }

    // =====================================================================
    // NotificationDispatched event
    // =====================================================================

    public function test_notification_dispatched_event_carries_all_required_data(): void
    {
        $notification = $this->createTestNotification();
        $log = NotificationLog::create([
            'type' => get_class($notification),
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
        ]);

        $event = new NotificationDispatched($notification, $this->user, $log);

        $this->assertSame($notification, $event->notification);
        $this->assertTrue($event->notifiable->is($this->user));
        $this->assertSame($log->id, $event->log->id);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function buildDispatcher(): NotificationDispatcher
    {
        return new NotificationDispatcher(
            driverRegistry: new DriverRegistry($this->app),
            rateLimiter: new ChannelRateLimiter,
        );
    }

    private function createTestNotification(array $data = []): BaseNotification
    {
        return new class($data) extends BaseNotification
        {
            public function __construct(private array $testData = []) {}

            public function toDatabase(object $notifiable): array
            {
                return array_merge([
                    'title' => 'Test Notification',
                    'body' => 'This is a test notification body',
                    'icon' => 'heroicon-o-bell',
                    'color' => 'primary',
                ], $this->testData);
            }
        };
    }

    /**
     * @param  Collection<int, NotificationChannel>  $channels
     */
    private function createExternalNotification(Collection $channels): BaseNotification
    {
        return new class($channels) extends BaseNotification implements HasExternalChannels
        {
            public function __construct(private Collection $externalChannelsList) {}

            public function toDatabase(object $notifiable): array
            {
                return [
                    'title' => 'External Test',
                    'body' => 'External notification body',
                ];
            }

            public function externalChannels(): Collection
            {
                return $this->externalChannelsList;
            }
        };
    }
}
