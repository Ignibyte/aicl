<?php

namespace Aicl\Database\Factories;

use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Aicl\States\AiAgent\Active;
use Aicl\States\AiAgent\Archived;
use Aicl\States\AiAgent\Draft;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiAgent>
 */
class AiAgentFactory extends Factory
{
    protected $model = AiAgent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $provider = fake()->randomElement(AiProvider::cases());

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional(0.8)->paragraph(),
            'provider' => $provider,
            'model' => $this->modelForProvider($provider),
            'system_prompt' => fake()->optional(0.7)->paragraph(2),
            'max_tokens' => fake()->randomElement([1024, 2048, 4096, 8192]),
            'temperature' => fake()->randomFloat(2, 0, 2),
            'context_window' => fake()->randomElement([32000, 64000, 128000, 200000]),
            'context_messages' => fake()->numberBetween(5, 50),
            'is_active' => fake()->boolean(70),
            'icon' => fake()->optional(0.5)->randomElement([
                'heroicon-o-cpu-chip',
                'heroicon-o-code-bracket',
                'heroicon-o-chart-bar',
                'heroicon-o-document-text',
                'heroicon-o-light-bulb',
            ]),
            'color' => fake()->optional(0.5)->hexColor(),
            'sort_order' => fake()->numberBetween(0, 10),
            'suggested_prompts' => fake()->optional(0.4)->randomElements([
                'Analyze the data',
                'Help me write code',
                'Summarize this document',
                'Debug this issue',
                'Explain this concept',
            ], fake()->numberBetween(1, 3)),
            'capabilities' => fake()->optional(0.3)->randomElements([
                'chat',
                'analyze_data',
                'generate_code',
                'summarize',
            ], fake()->numberBetween(1, 3)),
            'visible_to_roles' => null,
            'max_requests_per_minute' => fake()->optional(0.3)->numberBetween(5, 60),
            'state' => Draft::class,
        ];
    }

    private function modelForProvider(AiProvider $provider): string
    {
        return match ($provider) {
            AiProvider::OpenAi => fake()->randomElement(['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo']),
            AiProvider::Anthropic => fake()->randomElement(['claude-sonnet-4-20250514', 'claude-haiku-4-5-20251001']),
            AiProvider::Ollama => fake()->randomElement(['llama3.2', 'mistral', 'codellama']),
            AiProvider::Custom => 'custom-model',
        };
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => Active::class,
            'is_active' => true,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => Archived::class,
            'is_active' => false,
        ]);
    }

    public function forProvider(AiProvider $provider): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => $provider,
            'model' => $this->modelForProvider($provider),
        ]);
    }

    public function withPrompts(): static
    {
        return $this->state(fn (array $attributes): array => [
            'suggested_prompts' => [
                'Help me analyze data',
                'Write a report',
                'Debug this code',
            ],
        ]);
    }
}
