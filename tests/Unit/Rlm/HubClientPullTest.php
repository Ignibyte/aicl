<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Models\RlmFailure;
use Aicl\Rlm\HubClient;
use Aicl\Rlm\ProjectIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HubClientPullTest extends TestCase
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

        // Create user with ID 1 for hub pull operations that hardcode owner_id => 1
        User::factory()->create(['id' => 1]);

        $identity = new ProjectIdentity;
        $this->client = new HubClient($identity);
    }

    // --- pullPatterns ---

    public function test_pull_patterns_fetches_and_merges(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_patterns*' => Http::response([
                'data' => [
                    ['name' => 'model.timestamps', 'description' => 'Has timestamps', 'target' => 'model', 'check_regex' => '/timestamps/'],
                    ['name' => 'migration.uuid', 'description' => 'Uses UUID', 'target' => 'migration', 'check_regex' => '/uuid/'],
                ],
                'meta' => ['last_page' => 1],
            ], 200),
        ]);

        $result = $this->client->pullPatterns();

        $this->assertEquals(2, $result['received']);
        $this->assertEquals(2, $result['merged']);
    }

    public function test_pull_patterns_handles_pagination(): void
    {
        Http::fakeSequence('hub.example.com/api/v1/rlm_patterns*')
            ->push([
                'data' => [
                    ['name' => 'pattern.1', 'description' => 'First', 'target' => 'model', 'check_regex' => '/1/'],
                ],
                'meta' => ['last_page' => 2],
            ], 200)
            ->push([
                'data' => [
                    ['name' => 'pattern.2', 'description' => 'Second', 'target' => 'model', 'check_regex' => '/2/'],
                ],
                'meta' => ['last_page' => 2],
            ], 200);

        $result = $this->client->pullPatterns();

        $this->assertEquals(2, $result['received']);
        $this->assertEquals(2, $result['merged']);
    }

    public function test_pull_patterns_handles_connection_error(): void
    {
        Http::fake([
            'hub.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        $result = $this->client->pullPatterns();

        $this->assertEquals(0, $result['received']);
        $this->assertEquals(0, $result['merged']);
    }

    // --- pullFailures ---

    public function test_pull_failures_fetches_and_merges(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_failures*' => Http::response([
                'data' => [
                    ['failure_code' => 'BF-001', 'category' => 'scaffolding', 'title' => 'Test', 'severity' => 'high'],
                ],
                'meta' => ['last_page' => 1],
            ], 200),
        ]);

        $result = $this->client->pullFailures();

        $this->assertEquals(1, $result['received']);
        $this->assertEquals(1, $result['merged']);

        $failure = RlmFailure::query()->where('failure_code', 'BF-001')->first();
        $this->assertNotNull($failure);
        $this->assertTrue($failure->promoted_to_base);
    }

    public function test_pull_failures_skips_non_base_failures(): void
    {
        Http::fake([
            'hub.example.com/api/v1/rlm_failures*' => Http::response([
                'data' => [
                    ['failure_code' => 'BF-001', 'category' => 'scaffolding', 'title' => 'Base', 'severity' => 'high'],
                    ['failure_code' => 'PF-001', 'category' => 'project', 'title' => 'Project', 'severity' => 'low'],
                ],
                'meta' => ['last_page' => 1],
            ], 200),
        ]);

        $result = $this->client->pullFailures();

        $this->assertEquals(2, $result['received']);
        $this->assertEquals(1, $result['merged']);
    }

    // --- pullPreventionRules ---

    public function test_pull_prevention_rules_fetches_and_caches(): void
    {
        Http::fake([
            'hub.example.com/api/v1/prevention_rules*' => Http::response([
                'data' => [
                    ['rule_text' => 'Always define default state', 'trigger_context' => ['has_states' => true], 'confidence' => 0.9],
                    ['rule_text' => 'Use HasUuids trait', 'trigger_context' => ['has_uuid' => true], 'confidence' => 0.8],
                ],
                'meta' => ['last_page' => 1],
            ], 200),
        ]);

        $result = $this->client->pullPreventionRules();

        $this->assertEquals(2, $result['received']);
        $this->assertEquals(2, $result['cached']);
    }

    public function test_pull_prevention_rules_handles_empty_response(): void
    {
        Http::fake([
            'hub.example.com/api/v1/prevention_rules*' => Http::response([
                'data' => [],
                'meta' => ['last_page' => 1],
            ], 200),
        ]);

        $result = $this->client->pullPreventionRules();

        $this->assertEquals(0, $result['received']);
        $this->assertEquals(0, $result['cached']);
    }
}
