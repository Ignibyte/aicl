<?php

namespace Aicl\Tests\Feature\AI;

use Aicl\AI\AiProviderFactory;
use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;
use Tests\TestCase;

class AiProviderFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_from_agent_returns_openai_provider(): void
    {
        config(['aicl.ai.openai.api_key' => 'sk-test-key']);

        $agent = AiAgent::factory()->active()->create([
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o',
            'max_tokens' => 4096,
            'temperature' => '0.70',
        ]);

        $provider = AiProviderFactory::makeFromAgent($agent);

        $this->assertInstanceOf(OpenAI::class, $provider);
    }

    public function test_make_from_agent_returns_anthropic_provider(): void
    {
        config(['aicl.ai.anthropic.api_key' => 'sk-ant-test-key']);

        $agent = AiAgent::factory()->active()->create([
            'provider' => AiProvider::Anthropic,
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 8192,
            'temperature' => '0.50',
        ]);

        $provider = AiProviderFactory::makeFromAgent($agent);

        $this->assertInstanceOf(Anthropic::class, $provider);
    }

    public function test_make_from_agent_returns_null_when_not_configured(): void
    {
        config(['aicl.ai.openai.api_key' => null]);

        $agent = AiAgent::factory()->active()->create([
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o',
        ]);

        $provider = AiProviderFactory::makeFromAgent($agent);

        $this->assertNull($provider);
    }

    public function test_make_from_agent_uses_agent_model(): void
    {
        config(['aicl.ai.openai.api_key' => 'sk-test-key']);

        $agent = AiAgent::factory()->active()->create([
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o-mini',
        ]);

        $provider = AiProviderFactory::makeFromAgent($agent);

        $this->assertInstanceOf(OpenAI::class, $provider);
    }

    // ─── Legacy factory methods still work ──────────────────────

    public function test_make_returns_null_when_openai_key_missing(): void
    {
        config(['aicl.ai.openai.api_key' => null]);

        $this->assertNull(AiProviderFactory::make('openai'));
    }

    public function test_make_returns_null_when_anthropic_key_missing(): void
    {
        config(['aicl.ai.anthropic.api_key' => null]);

        $this->assertNull(AiProviderFactory::make('anthropic'));
    }

    public function test_is_configured_checks_openai_key(): void
    {
        config(['aicl.ai.openai.api_key' => 'sk-test']);
        $this->assertTrue(AiProviderFactory::isConfigured('openai'));

        config(['aicl.ai.openai.api_key' => null]);
        $this->assertFalse(AiProviderFactory::isConfigured('openai'));
    }

    public function test_is_configured_checks_anthropic_key(): void
    {
        config(['aicl.ai.anthropic.api_key' => 'sk-ant-test']);
        $this->assertTrue(AiProviderFactory::isConfigured('anthropic'));

        config(['aicl.ai.anthropic.api_key' => null]);
        $this->assertFalse(AiProviderFactory::isConfigured('anthropic'));
    }
}
