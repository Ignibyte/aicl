<?php

namespace Aicl\AI;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;

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

        return new Anthropic(
            key: $key,
            model: config('aicl.ai.anthropic.model', 'claude-haiku-4-5-20251001'),
        );
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
