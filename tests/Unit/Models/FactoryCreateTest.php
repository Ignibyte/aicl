<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\DomainEventRecord;
use Aicl\Models\FailedJob;
use Aicl\Models\NotificationLog;
use Aicl\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * S-005: Verify all 4 non-RLM factories can create() without errors.
 */
class FactoryCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Hub models require owner_id => 1
        User::factory()->create(['id' => 1]);
    }

    // ========================================================================
    // NotificationLog
    // ========================================================================

    public function test_notification_log_factory_creates_record(): void
    {
        $log = NotificationLog::factory()->create();

        $this->assertDatabaseHas('notification_logs', ['id' => $log->id]);
        $this->assertNotNull($log->type);
        $this->assertIsArray($log->channels);
        $this->assertIsArray($log->channel_status);
        $this->assertIsArray($log->data);
    }

    public function test_notification_log_factory_read_state(): void
    {
        $log = NotificationLog::factory()->read()->create();

        $this->assertNotNull($log->read_at);
    }

    public function test_notification_log_factory_failed_state(): void
    {
        $log = NotificationLog::factory()->failed()->create();

        /** @phpstan-ignore-next-line */
        $this->assertSame('failed', $log->channel_status['mail']);
    }

    // ========================================================================
    // SocialAccount
    // ========================================================================

    public function test_social_account_factory_creates_record(): void
    {
        $account = SocialAccount::factory()->create();

        $this->assertDatabaseHas('social_accounts', ['id' => $account->id]);
    }

    public function test_social_account_factory_expired_state(): void
    {
        $account = SocialAccount::factory()->expired()->create();

        $this->assertTrue($account->isExpired());
    }

    // ========================================================================
    // FailedJob
    // ========================================================================

    public function test_failed_job_factory_creates_record(): void
    {
        $job = FailedJob::factory()->create();

        $this->assertDatabaseHas('failed_jobs', ['id' => $job->id]);
    }

    public function test_failed_job_factory_payload_is_array(): void
    {
        $job = FailedJob::factory()->create();

        /** @phpstan-ignore-next-line - PHPDoc says string but Eloquent 'array' cast makes it array */
        $this->assertArrayHasKey('displayName', $job->payload);
    }

    // ========================================================================
    // DomainEventRecord
    // ========================================================================

    public function test_domain_event_record_factory_creates_record(): void
    {
        $record = DomainEventRecord::factory()->create();

        $this->assertDatabaseHas('domain_events', ['id' => $record->id]);
    }

    public function test_domain_event_record_factory_by_system_state(): void
    {
        $record = DomainEventRecord::factory()->bySystem()->create();

        $this->assertSame('system', $record->actor_type);
        $this->assertNull($record->actor_id);
    }

    public function test_domain_event_record_factory_by_user_state(): void
    {
        $record = DomainEventRecord::factory()->byUser(1)->create();

        $this->assertSame('user', $record->actor_type);
        $this->assertSame(1, $record->actor_id);
    }

    // ========================================================================
    // Batch creation
    // ========================================================================

    public function test_all_four_factories_can_create_multiple_records(): void
    {
        NotificationLog::factory()->count(3)->create();
        SocialAccount::factory()->count(3)->create();
        FailedJob::factory()->count(3)->create();
        DomainEventRecord::factory()->count(3)->create();

        $this->assertGreaterThanOrEqual(3, NotificationLog::count());
        $this->assertGreaterThanOrEqual(3, SocialAccount::count());
        $this->assertGreaterThanOrEqual(3, FailedJob::count());
        $this->assertGreaterThanOrEqual(3, DomainEventRecord::count());
    }
}
