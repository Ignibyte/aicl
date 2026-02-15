<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\DomainEventRecord;
use Aicl\Models\FailedJob;
use Aicl\Models\KnowledgeLink;
use Aicl\Models\NotificationLog;
use Aicl\Models\RlmSemanticCache;
use Aicl\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * S-005: Verify all 6 new factories can create() without errors.
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

        $this->assertSame('failed', $log->channel_status['mail']);
    }

    // ========================================================================
    // SocialAccount
    // ========================================================================

    public function test_social_account_factory_creates_record(): void
    {
        $account = SocialAccount::factory()->create();

        $this->assertDatabaseHas('social_accounts', ['id' => $account->id]);
        $this->assertNotNull($account->provider);
        $this->assertNotNull($account->provider_id);
        $this->assertNotNull($account->user_id);
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
        $this->assertNotNull($job->uuid);
        $this->assertNotNull($job->connection);
        $this->assertNotNull($job->queue);
        $this->assertNotNull($job->exception);
        $this->assertNotNull($job->failed_at);
    }

    public function test_failed_job_factory_payload_is_array(): void
    {
        $job = FailedJob::factory()->create();

        $this->assertIsArray($job->payload);
        $this->assertArrayHasKey('displayName', $job->payload);
    }

    // ========================================================================
    // DomainEventRecord
    // ========================================================================

    public function test_domain_event_record_factory_creates_record(): void
    {
        $record = DomainEventRecord::factory()->create();

        $this->assertDatabaseHas('domain_events', ['id' => $record->id]);
        $this->assertNotNull($record->event_type);
        $this->assertNotNull($record->actor_type);
        $this->assertIsArray($record->payload);
        $this->assertIsArray($record->metadata);
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
    // KnowledgeLink
    // ========================================================================

    public function test_knowledge_link_factory_creates_record(): void
    {
        $link = KnowledgeLink::factory()->create();

        $this->assertDatabaseHas('knowledge_links', ['id' => $link->id]);
        $this->assertNotNull($link->source_type);
        $this->assertNotNull($link->source_id);
        $this->assertNotNull($link->target_type);
        $this->assertNotNull($link->target_id);
        $this->assertNotNull($link->relationship);
        $this->assertNotNull($link->confidence);
    }

    public function test_knowledge_link_factory_high_confidence_state(): void
    {
        $link = KnowledgeLink::factory()->highConfidence()->create();

        $this->assertGreaterThanOrEqual(0.8, (float) $link->confidence);
    }

    // ========================================================================
    // RlmSemanticCache
    // ========================================================================

    public function test_rlm_semantic_cache_factory_creates_record(): void
    {
        $cache = RlmSemanticCache::factory()->create();

        $this->assertDatabaseHas('rlm_semantic_cache', ['id' => $cache->id]);
        $this->assertNotNull($cache->cache_key);
        $this->assertNotNull($cache->check_name);
        $this->assertNotNull($cache->entity_name);
        $this->assertIsBool($cache->passed);
        $this->assertNotNull($cache->message);
        $this->assertNotNull($cache->files_hash);
    }

    public function test_rlm_semantic_cache_factory_expired_state(): void
    {
        $cache = RlmSemanticCache::factory()->expired()->create();

        $this->assertTrue($cache->expires_at->isPast());
    }

    public function test_rlm_semantic_cache_factory_passing_state(): void
    {
        $cache = RlmSemanticCache::factory()->passing()->create();

        $this->assertTrue($cache->passed);
        $this->assertGreaterThanOrEqual(0.8, (float) $cache->confidence);
    }

    public function test_rlm_semantic_cache_factory_failing_state(): void
    {
        $cache = RlmSemanticCache::factory()->failing()->create();

        $this->assertFalse($cache->passed);
    }

    // ========================================================================
    // Batch creation
    // ========================================================================

    public function test_all_six_factories_can_create_multiple_records(): void
    {
        NotificationLog::factory()->count(3)->create();
        SocialAccount::factory()->count(3)->create();
        FailedJob::factory()->count(3)->create();
        DomainEventRecord::factory()->count(3)->create();
        KnowledgeLink::factory()->count(3)->create();
        RlmSemanticCache::factory()->count(3)->create();

        // Use >= because observers on related models (e.g. KnowledgeLink creates
        // RlmFailure + RlmLesson which dispatch domain events) may insert
        // additional DomainEventRecords.
        $this->assertGreaterThanOrEqual(3, NotificationLog::count());
        $this->assertGreaterThanOrEqual(3, SocialAccount::count());
        $this->assertGreaterThanOrEqual(3, FailedJob::count());
        $this->assertGreaterThanOrEqual(3, DomainEventRecord::count());
        $this->assertGreaterThanOrEqual(3, KnowledgeLink::count());
        $this->assertGreaterThanOrEqual(3, RlmSemanticCache::count());
    }
}
