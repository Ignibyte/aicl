<?php

namespace Aicl\Database\Factories;

use Aicl\Enums\AiMessageRole;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
    protected $model = AiMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'role' => fake()->randomElement([AiMessageRole::User, AiMessageRole::Assistant]),
            'content' => fake()->paragraph(2),
            'token_count' => fake()->numberBetween(10, 2000),
            'metadata' => null,
        ];
    }

    public function fromUser(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => AiMessageRole::User,
        ]);
    }

    public function fromAssistant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => AiMessageRole::Assistant,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => AiMessageRole::System,
            'content' => fake()->sentence(),
        ]);
    }

    public function withMetadata(): static
    {
        return $this->state(fn (array $attributes): array => [
            'metadata' => [
                'model' => 'gpt-4o',
                'finish_reason' => 'stop',
                'prompt_tokens' => fake()->numberBetween(50, 500),
                'completion_tokens' => fake()->numberBetween(50, 1000),
            ],
        ]);
    }
}
