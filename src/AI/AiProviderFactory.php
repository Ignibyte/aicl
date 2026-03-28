<?php

declare(strict_types=1);

namespace Aicl\AI;

use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;

/**
 * Factory for creating AI provider instances (OpenAI, Anthropic, Ollama).
 *
 * Resolves the configured AI provider and creates NeuronAI provider instances
 * from either global config (aicl.ai.*) or per-agent settings. Supports
 * OpenAI, Anthropic, and Ollama drivers with credential validation.
 *
 * @see AiAgent  Agent model with per-agent provider configuration
 * @see AiProvider  Enum of supported AI providers
 */
final class AiProviderFactory
{
    /**
     * Resolve the AI provider from config.
     *
     * Returns null if the provider is not configured (no API key).
     */
    public static function make(?string $driver = null): ?AIProviderInterface
    {
        $driver ??= config('aicl.ai.provider', 'openai');

        return match ($driver) {
            'openai' => self::makeOpenAi(),
            'anthropic' => self::makeAnthropic(),
            'ollama' => self::makeOllama(),
            default => null,
        };
    }

    /**
     * Check if the configured provider has valid credentials.
     */
    public static function isConfigured(?string $driver = null): bool
    {
        $driver ??= config('aicl.ai.provider', 'openai');

        return match ($driver) {
            'openai' => ! empty(config('aicl.ai.openai.api_key')),
            'anthropic' => ! empty(config('aicl.ai.anthropic.api_key')),
            'ollama' => ! empty(config('aicl.ai.ollama.host')),
            default => false,
        };
    }

    /**
     * Create a provider instance from an AiAgent's configuration.
     *
     * Uses the agent's provider, model, temperature, and max_tokens
     * instead of global config. API keys are still read from config.
     */
    public static function makeFromAgent(AiAgent $agent): ?AIProviderInterface
    {
        $driver = $agent->provider->value;

        if (! self::isConfigured($driver)) {
            return null;
        }

        $parameters = [];

        if ($agent->temperature) {
            $parameters['temperature'] = (float) $agent->temperature;
        }

        return match ($agent->provider) {
            AiProvider::OpenAi => new OpenAI(
                key: config('aicl.ai.openai.api_key'),
                model: $agent->model,
                parameters: array_filter([
                    ...$parameters,
                    'max_tokens' => $agent->max_tokens > 0 ? $agent->max_tokens : null,
                ]),
            ),
            AiProvider::Anthropic => new Anthropic(
                key: config('aicl.ai.anthropic.api_key'),
                model: $agent->model,
                max_tokens: $agent->max_tokens > 0 ? $agent->max_tokens : 8192,
                parameters: $parameters,
            ),
            AiProvider::Ollama => new Ollama(
                url: rtrim(config('aicl.ai.ollama.host', 'http://localhost:11434'), '/').'/api',
                model: $agent->model,
                parameters: $parameters,
            ),
            AiProvider::Custom => self::make($driver),
        };
    }

    private static function makeOpenAi(): ?OpenAI
    {
        $key = config('aicl.ai.openai.api_key');

        if (empty($key)) {
            return null;
        }

        return new OpenAI(
            key: $key,
            model: config('aicl.ai.openai.model', 'gpt-4o-mini'),
        );
    }

    private static function makeAnthropic(): ?Anthropic
    {
        $key = config('aicl.ai.anthropic.api_key');

        if (empty($key)) {
            return null;
        }

        // @codeCoverageIgnoreStart — AI provider dependency
        return new Anthropic(
            key: $key,
            model: config('aicl.ai.anthropic.model', 'claude-haiku-4-5-20251001'),
        );
        // @codeCoverageIgnoreEnd
    }

    private static function makeOllama(): Ollama
    {
        $host = config('aicl.ai.ollama.host', 'http://localhost:11434');

        return new Ollama(
            url: rtrim($host, '/').'/api',
            model: config('aicl.ai.ollama.model', 'llama3.2'),
        );
    }
}
