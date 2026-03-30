<?php

declare(strict_types=1);

namespace Aicl\Database\Factories;

use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\States\AiConversation\Active;
use Aicl\States\AiConversation\Archived;
use Aicl\States\AiConversation\Summarized;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiConversation>
 */
class AiConversationFactory extends Factory
{
    protected $model = AiConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->optional(0.8)->sentence(4),
            'user_id' => User::factory(),
            'ai_agent_id' => AiAgent::factory(),
            'message_count' => fake()->numberBetween(0, 100),
            'token_count' => fake()->numberBetween(0, 50000),
            'summary' => null,
            'is_pinned' => fake()->boolean(10),
            'context_page' => fake()->optional(0.3)->randomElement([
                '/admin',
                '/admin/users',
                '/admin/ai-agents',
                '/admin/settings',
            ]),
            'last_message_at' => fake()->optional(0.9)->dateTimeBetween('-30 days'),
            'state' => Active::class,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => Active::class,
        ]);
    }

    public function summarized(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => Summarized::class,
            'summary' => fake()->paragraph(3),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => Archived::class,
        ]);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_pinned' => true,
        ]);
    }

    public function forAgent(AiAgent $agent): static
    {
        return $this->state(fn (array $attributes): array => [
            'ai_agent_id' => $agent->id,
        ]);
    }
}
