<?php

namespace Aicl\Tests\Unit\AI;

use Aicl\AI\AiProviderFactory;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;
use Tests\TestCase;

class AiProviderFactoryTest extends TestCase
{
    public function test_make_returns_openai_provider_when_configured(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
            'aicl.ai.openai.model' => 'gpt-4o-mini',
        ]);

        $provider = AiProviderFactory::make();

        $this->assertInstanceOf(OpenAI::class, $provider);
    }

    public function test_make_returns_anthropic_provider_when_configured(): void
    {
        config([
            'aicl.ai.provider' => 'anthropic',
            'aicl.ai.anthropic.api_key' => 'sk-ant-test',
            'aicl.ai.anthropic.model' => 'claude-haiku-4-5-20251001',
        ]);

        $provider = AiProviderFactory::make('anthropic');

        $this->assertInstanceOf(Anthropic::class, $provider);
    }

    public function test_make_returns_ollama_provider(): void
    {
        config([
            'aicl.ai.provider' => 'ollama',
            'aicl.ai.ollama.host' => 'http://localhost:11434',
            'aicl.ai.ollama.model' => 'llama3.2',
        ]);

        $provider = AiProviderFactory::make('ollama');

        $this->assertInstanceOf(Ollama::class, $provider);
    }

    public function test_make_returns_null_for_openai_without_api_key(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => null,
        ]);

        $provider = AiProviderFactory::make();

        $this->assertNull($provider);
    }

    public function test_make_returns_null_for_anthropic_without_api_key(): void
    {
        config([
            'aicl.ai.provider' => 'anthropic',
            'aicl.ai.anthropic.api_key' => null,
        ]);

        $provider = AiProviderFactory::make('anthropic');

        $this->assertNull($provider);
    }

    public function test_make_returns_null_for_unknown_driver(): void
    {
        $provider = AiProviderFactory::make('nonexistent');

        $this->assertNull($provider);
    }

    public function test_is_configured_returns_true_for_openai_with_key(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test',
        ]);

        $this->assertTrue(AiProviderFactory::isConfigured());
    }

    public function test_is_configured_returns_false_for_openai_without_key(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => null,
        ]);

        $this->assertFalse(AiProviderFactory::isConfigured());
    }

    public function test_is_configured_returns_true_for_ollama_with_host(): void
    {
        config([
            'aicl.ai.provider' => 'ollama',
            'aicl.ai.ollama.host' => 'http://localhost:11434',
        ]);

        $this->assertTrue(AiProviderFactory::isConfigured('ollama'));
    }

    public function test_driver_parameter_overrides_config(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.anthropic.api_key' => 'sk-ant-test',
        ]);

        $provider = AiProviderFactory::make('anthropic');

        $this->assertInstanceOf(Anthropic::class, $provider);
    }
}
