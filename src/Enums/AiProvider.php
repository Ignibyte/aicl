<?php

namespace Aicl\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case Ollama = 'ollama';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::Ollama => 'Ollama',
            self::Custom => 'Custom',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OpenAi => 'success',
            self::Anthropic => 'info',
            self::Ollama => 'warning',
            self::Custom => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OpenAi => 'heroicon-o-bolt',
            self::Anthropic => 'heroicon-o-cpu-chip',
            self::Ollama => 'heroicon-o-server',
            self::Custom => 'heroicon-o-cog-6-tooth',
        };
    }
}
