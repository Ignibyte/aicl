<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Models\GenerationTrace;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Rlm\HubClient;
use Aicl\Rlm\ProjectIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HubClientOfflineTest extends TestCase
{
    use RefreshDatabase;

    private HubClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'aicl.rlm.hub.enabled' => true,
            'aicl.rlm.hub.url' => 'https://hub.example.com',
            'aicl.rlm.hub.token' => 'test-token-123',
            'aicl.rlm.hub.timeout' => 10,
        ]);

        Cache::forget('rlm:hub:sync_queue');

        $identity = new ProjectIdentity;
        $this->client = new HubClient($identity);
    }

    // --- End-to-end offline/retry scenarios ---

    public function test_queued_items_drain_when_hub_comes_online(): void
    {
        $this->client->enqueue('rlm_failures/upsert', [
            'failure_code' => 'BF-001',
            'title' => 'Offline failure',
            'category' => 'scaffolding',
            'severity' => 'high',
        ]);
        $this->assertEquals(1, $this->client->getQueueSize());

        Http::fake([
            'hub.example.com/*' => Http::response(['data' => []], 201),
        ]);

        $drainResult = $this->client->drainQueue();
        $this->assertEquals(1, $drainResult['pushed']);
        $this->assertEquals(0, $drainResult['remaining']);
    }

    public function test_multiple_queued_items_all_drain(): void
    {
        $this->client->enqueue('rlm_failures/upsert', ['failure_code' => 'BF-001', 'title' => 'Failure 1']);
        $this->client->enqueue('rlm_failures/upsert', ['failure_code' => 'BF-002', 'title' => 'Failure 2']);
        $this->client->enqueue('rlm_lessons', ['summary' => 'Lesson 1', 'topic' => 'testing']);

        $this->assertEquals(3, $this->client->getQueueSize());

        Http::fake([
            'hub.example.com/*' => Http::response(['data' => []], 201),
        ]);

        $result = $this->client->drainQueue();
        $this->assertEquals(3, $result['pushed']);
        $this->assertEquals(0, $result['remaining']);
    }

    public function test_partial_drain_when_hub_goes_offline_mid_drain(): void
    {
        $this->client->enqueue('rlm_failures/upsert', ['title' => 'Item 1', 'failure_code' => 'BF-001']);
        $this->client->enqueue('rlm_failures/upsert', ['title' => 'Item 2', 'failure_code' => 'BF-002']);
        $this->client->enqueue('rlm_failures/upsert', ['title' => 'Item 3', 'failure_code' => 'BF-003']);

        Http::fakeSequence('hub.example.com/*')
            ->push(['data' => []], 201)
            ->whenEmpty(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection lost'));

        $result = $this->client->drainQueue();

        $this->assertEquals(1, $result['pushed']);
        $this->assertEquals(1, $result['errors']);
        $this->assertEquals(2, $result['remaining']);
    }

    public function test_push_failures_mixes_success_and_offline(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => 'scaffolding',
            'title' => 'First',
            'severity' => 'high',
        ]);
        RlmFailure::factory()->create([
            'failure_code' => 'BF-002',
            'category' => 'testing',
            'title' => 'Second',
            'severity' => 'medium',
        ]);

        Http::fakeSequence('hub.example.com/*')
            ->push(['data' => []], 201)
            ->whenEmpty(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection lost'));

        $result = $this->client->pushFailures();

        $this->assertEquals(1, $result['pushed']);
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(1, $result['queued']);
        $this->assertEquals(1, $this->client->getQueueSize());
    }

    public function test_push_lessons_queues_on_offline(): void
    {
        Http::fake([
            'hub.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        RlmLesson::factory()->create([
            'topic' => 'testing',
            'summary' => 'Test lesson',
            'detail' => 'Detail',
        ]);

        $result = $this->client->pushLessons();

        $this->assertEquals(0, $result['pushed']);
        $this->assertEquals(1, $result['queued']);
        $this->assertEquals(1, $this->client->getQueueSize());
    }

    public function test_push_traces_queues_on_offline(): void
    {
        Http::fake([
            'hub.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        GenerationTrace::factory()->create([
            'entity_name' => 'TestEntity',
            'scaffolder_args' => '--fields name:string',
        ]);

        $result = $this->client->pushTraces();

        $this->assertEquals(0, $result['pushed']);
        $this->assertEquals(1, $result['queued']);
    }

    public function test_queue_preserves_payload_data(): void
    {
        Http::fake([
            'hub.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-PRESERVE',
            'category' => 'scaffolding',
            'title' => 'Preserved failure',
            'severity' => 'high',
            'description' => 'This data should survive the queue',
        ]);

        $this->client->pushFailures();

        $items = $this->client->dequeue(10);
        $this->assertCount(1, $items);

        $payload = $items[0]['payload'];
        $this->assertEquals('BF-PRESERVE', $payload['failure_code']);
        $this->assertEquals('Preserved failure', $payload['title']);
    }

    public function test_stats_shows_knowledge_summary(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'stats'])
            ->assertSuccessful()
            ->expectsOutputToContain('Knowledge System Summary');
    }
}
