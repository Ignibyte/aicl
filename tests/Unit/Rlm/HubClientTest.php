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

class HubClientTest extends TestCase
{
    use RefreshDatabase;

    private HubClient $client;

    private ProjectIdentity $identity;

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

        $this->identity = new ProjectIdentity;
        $this->client = new HubClient($this->identity);
    }

    // --- isReachable ---

    public function test_is_reachable_returns_true_on_success(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_patterns*' => Http::response(['data' => []], 200),
        ]);

        $this->assertTrue($this->client->isReachable());
    }

    public function test_is_reachable_returns_false_when_hub_disabled(): void
    {
        config(['aicl.rlm.hub.enabled' => false]);
        $identity = new ProjectIdentity;
        $client = new HubClient($identity);

        $this->assertFalse($client->isReachable());
    }

    public function test_is_reachable_returns_false_on_connection_error(): void
    {
        Http::fake([
            'hub.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        $this->assertFalse($this->client->isReachable());
    }

    // --- pushFailures ---

    public function test_push_failures_sends_to_hub(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_failures/upsert' => Http::response(['data' => []], 201),
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => 'scaffolding',
            'title' => 'Test failure',
            'severity' => 'high',
        ]);

        $result = $this->client->pushFailures();

        $this->assertEquals(1, $result['pushed']);
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(0, $result['queued']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'rlm_failures/upsert'));
    }

    public function test_push_failures_includes_project_hash(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_failures/upsert' => Http::response(['data' => []], 201),
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => 'scaffolding',
            'title' => 'Test failure',
            'severity' => 'high',
        ]);

        $this->client->pushFailures();

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return isset($body['project_hash'])
                && strlen($body['project_hash']) === 64;
        });
    }

    public function test_push_failures_counts_errors(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_failures/upsert' => Http::response(['error' => 'forbidden'], 403),
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => 'scaffolding',
            'title' => 'Test failure',
            'severity' => 'high',
        ]);

        $result = $this->client->pushFailures();

        $this->assertEquals(0, $result['pushed']);
        $this->assertEquals(1, $result['errors']);
    }

    public function test_push_failures_queues_on_connection_error(): void
    {
        Http::fake([
            'hub.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => 'scaffolding',
            'title' => 'Test failure',
            'severity' => 'high',
        ]);

        $result = $this->client->pushFailures();

        $this->assertEquals(0, $result['pushed']);
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(1, $result['queued']);
        $this->assertEquals(1, $this->client->getQueueSize());
    }

    // --- pushLessons ---

    public function test_push_lessons_sends_to_hub(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_lessons' => Http::response(['data' => []], 201),
        ]);

        RlmLesson::factory()->create([
            'topic' => 'testing',
            'summary' => 'Test lesson',
            'detail' => 'Detail text',
        ]);

        $result = $this->client->pushLessons();

        $this->assertEquals(1, $result['pushed']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'rlm_lessons'));
    }

    // --- pushTraces ---

    public function test_push_traces_sends_to_hub(): void
    {
        Http::fake([
            'hub.example.com/api/v1/generation_traces' => Http::response(['data' => []], 201),
        ]);

        GenerationTrace::factory()->create([
            'entity_name' => 'TestEntity',
            'scaffolder_args' => '--fields name:string',
        ]);

        $result = $this->client->pushTraces();

        $this->assertEquals(1, $result['pushed']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'generation_traces'));
    }

    // --- drainQueue ---

    public function test_drain_queue_retries_queued_items(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_failures/upsert' => Http::response(['data' => []], 201),
        ]);

        $this->client->enqueue('rlm_failures/upsert', ['title' => 'Queued failure', 'failure_code' => 'BF-001']);

        $result = $this->client->drainQueue();

        $this->assertEquals(1, $result['pushed']);
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(0, $result['remaining']);
    }

    public function test_drain_queue_stops_on_connection_error(): void
    {
        Http::fake([
            'hub.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        $this->client->enqueue('endpoint1', ['data' => 1]);
        $this->client->enqueue('endpoint2', ['data' => 2]);

        $result = $this->client->drainQueue();

        $this->assertEquals(0, $result['pushed']);
        $this->assertEquals(1, $result['errors']);
        $this->assertEquals(2, $result['remaining']);
    }

    // --- anonymization ---

    public function test_push_failures_anonymizes_data(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_failures/upsert' => Http::response(['data' => []], 201),
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => 'scaffolding',
            'title' => 'Test failure',
            'severity' => 'high',
            'description' => 'A test failure',
        ]);

        $this->client->pushFailures();

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return isset($body['failure_code'])
                && isset($body['title'])
                && ! isset($body['source_code']);
        });
    }
}
