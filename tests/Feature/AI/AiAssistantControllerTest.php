<?php

namespace Aicl\Tests\Feature\AI;

use Aicl\AI\Jobs\AiStreamJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiAssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('viewer');

        Bus::fake([AiStreamJob::class]);
    }

    // ── Provider checks ────────────────────────────────────────────

    public function test_ask_returns_422_when_provider_not_configured(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), [
                'prompt' => 'Hello world',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error'])
            ->assertJsonFragment(['error' => 'AI provider not configured. Set the appropriate API key (e.g. OPENAI_API_KEY, ANTHROPIC_API_KEY) in your environment.']);
    }

    public function test_ask_returns_403_for_non_admin(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
        ]);

        $response = $this->actingAs($this->viewer)
            ->postJson(route('api.v1.ai.ask'), [
                'prompt' => 'Hello world',
            ]);

        $response->assertForbidden();
    }

    // ── Validation ─────────────────────────────────────────────────

    public function test_ask_validates_prompt_required(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('prompt');
    }

    public function test_ask_validates_prompt_max_length(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
            'aicl.ai.max_prompt_length' => 100,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), [
                'prompt' => str_repeat('a', 101),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('prompt');
    }

    public function test_ask_validates_entity_id_required_with_entity_type(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), [
                'prompt' => 'Tell me about this',
                'entity_type' => 'App\\Models\\User',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('entity_id');
    }

    // ── Entity context ─────────────────────────────────────────────

    public function test_ask_returns_404_for_nonexistent_entity(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), [
                'prompt' => 'Tell me about this',
                'entity_type' => 'App\\Models\\NonexistentModel',
                'entity_id' => '999',
            ]);

        $response->assertNotFound()
            ->assertJsonFragment(['error' => 'Entity not found or does not support AI context.']);
    }

    // ── Job dispatch + streaming ───────────────────────────────────

    public function test_ask_dispatches_job_and_returns_stream_info(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), [
                'prompt' => 'Hello world',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['stream_id', 'channel']);

        $streamId = $response->json('stream_id');
        $this->assertTrue(Str::isUuid($streamId));
        $this->assertSame("private-ai.stream.{$streamId}", $response->json('channel'));

        Bus::assertDispatched(AiStreamJob::class, function (AiStreamJob $job) use ($streamId): bool {
            return $job->streamId === $streamId
                && $job->userId === $this->admin->id
                && $job->prompt === 'Hello world';
        });
    }

    public function test_ask_stores_user_in_cache_for_channel_auth(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), [
                'prompt' => 'Hello world',
            ]);

        $streamId = $response->json('stream_id');

        $this->assertSame($this->admin->id, Cache::get("ai-stream:{$streamId}:user"));
    }

    public function test_ask_increments_concurrent_count(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), ['prompt' => 'First']);

        $this->assertSame(1, (int) Cache::get("ai-stream:user:{$this->admin->id}:count"));

        $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), ['prompt' => 'Second']);

        $this->assertSame(2, (int) Cache::get("ai-stream:user:{$this->admin->id}:count"));
    }

    public function test_ask_returns_429_when_concurrent_limit_reached(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
            'aicl.ai.streaming.max_concurrent_per_user' => 2,
        ]);

        Cache::put("ai-stream:user:{$this->admin->id}:count", 2, 300);

        $response = $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), [
                'prompt' => 'Hello world',
            ]);

        $response->assertStatus(429)
            ->assertJsonFragment(['error' => 'Too many concurrent AI streams. Please wait for a current stream to finish.']);

        Bus::assertNotDispatched(AiStreamJob::class);
    }

    public function test_ask_passes_system_prompt_from_request(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('api.v1.ai.ask'), [
                'prompt' => 'Hello',
                'system_prompt' => 'Be a pirate',
            ]);

        Bus::assertDispatched(AiStreamJob::class, function (AiStreamJob $job): bool {
            return $job->systemPrompt === 'Be a pirate';
        });
    }
}
