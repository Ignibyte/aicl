<?php

namespace Aicl\Tests\Feature\Http;

use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * S-010: Auth rejection tests for all hub entity API controllers.
 *
 * Every API endpoint must reject unauthenticated requests with 401.
 */
class ApiAuthRejectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Create owner for factory records
        User::factory()->create(['id' => 1]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function unauthenticatedEndpointProvider(): array
    {
        return [
            'GET rlm_patterns' => ['GET', '/api/v1/rlm_patterns'],
            'POST rlm_patterns' => ['POST', '/api/v1/rlm_patterns'],
            'GET rlm_failures' => ['GET', '/api/v1/rlm_failures'],
            'POST rlm_failures' => ['POST', '/api/v1/rlm_failures'],
            'POST rlm_failures/upsert' => ['POST', '/api/v1/rlm_failures/upsert'],
            'GET rlm_failures/top' => ['GET', '/api/v1/rlm_failures/top'],
            'GET rlm_lessons' => ['GET', '/api/v1/rlm_lessons'],
            'POST rlm_lessons' => ['POST', '/api/v1/rlm_lessons'],
            'GET rlm_lessons/search' => ['GET', '/api/v1/rlm_lessons/search?q=test'],
            'GET failure_reports' => ['GET', '/api/v1/failure_reports'],
            'POST failure_reports' => ['POST', '/api/v1/failure_reports'],
            'GET generation_traces' => ['GET', '/api/v1/generation_traces'],
            'POST generation_traces' => ['POST', '/api/v1/generation_traces'],
            'GET prevention_rules' => ['GET', '/api/v1/prevention_rules'],
            'POST prevention_rules' => ['POST', '/api/v1/prevention_rules'],
            'GET prevention_rules/for-entity' => ['GET', '/api/v1/prevention_rules/for-entity'],
            'GET rlm_scores' => ['GET', '/api/v1/rlm_scores'],
            'POST rlm_scores' => ['POST', '/api/v1/rlm_scores'],
            'GET rlm/search' => ['GET', '/api/v1/rlm/search?q=test'],
            'GET rlm/recall' => ['GET', '/api/v1/rlm/recall?agent=architect&phase=3'],
            'POST rlm/embed' => ['POST', '/api/v1/rlm/embed'],
        ];
    }

    #[DataProvider('unauthenticatedEndpointProvider')]
    public function test_unauthenticated_request_returns_401(string $method, string $uri): void
    {
        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, []),
            'PUT' => $this->putJson($uri, []),
            'DELETE' => $this->deleteJson($uri),
        };

        $response->assertStatus(401);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function unauthenticatedSingleRecordProvider(): array
    {
        return [
            'GET single rlm_pattern' => ['GET', '/api/v1/rlm_patterns/', 'rlm_pattern'],
            'PUT single rlm_pattern' => ['PUT', '/api/v1/rlm_patterns/', 'rlm_pattern'],
            'DELETE single rlm_pattern' => ['DELETE', '/api/v1/rlm_patterns/', 'rlm_pattern'],
            'GET single rlm_failure' => ['GET', '/api/v1/rlm_failures/', 'rlm_failure'],
            'PUT single rlm_failure' => ['PUT', '/api/v1/rlm_failures/', 'rlm_failure'],
            'DELETE single rlm_failure' => ['DELETE', '/api/v1/rlm_failures/', 'rlm_failure'],
            'GET single rlm_lesson' => ['GET', '/api/v1/rlm_lessons/', 'rlm_lesson'],
            'GET single generation_trace' => ['GET', '/api/v1/generation_traces/', 'generation_trace'],
            'GET single prevention_rule' => ['GET', '/api/v1/prevention_rules/', 'prevention_rule'],
            'GET single rlm_score' => ['GET', '/api/v1/rlm_scores/', 'rlm_score'],
        ];
    }

    #[DataProvider('unauthenticatedSingleRecordProvider')]
    public function test_unauthenticated_single_record_request_returns_401(string $method, string $uri, string $type): void
    {
        // Use a fake UUID — 401 should fire before 404
        $fakeId = '00000000-0000-0000-0000-000000000001';

        $response = match ($method) {
            'GET' => $this->getJson($uri.$fakeId),
            'PUT' => $this->putJson($uri.$fakeId, []),
            'DELETE' => $this->deleteJson($uri.$fakeId),
        };

        $response->assertStatus(401);
    }

    // ========================================================================
    // Knowledge endpoint specific auth tests
    // ========================================================================

    public function test_rlm_search_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/rlm/search?q=test+query');

        $response->assertStatus(401);
    }

    public function test_rlm_recall_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/rlm/recall?agent=architect&phase=3');

        $response->assertStatus(401);
    }

    public function test_rlm_failure_lookup_requires_authentication(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);

        $response = $this->getJson("/api/v1/rlm/failure/{$failure->failure_code}");

        $response->assertStatus(401);
    }

    public function test_rlm_embed_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/rlm/embed', [
            'model' => 'RlmFailure',
            'ids' => ['00000000-0000-0000-0000-000000000001'],
        ]);

        $response->assertStatus(401);
    }
}
