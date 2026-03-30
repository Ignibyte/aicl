<?php

declare(strict_types=1);

namespace Aicl\Database\Factories;

use Aicl\Events\Enums\ActorType;
use Aicl\Models\DomainEventRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DomainEventRecord>
 */
class DomainEventRecordFactory extends Factory
{
    protected $model = DomainEventRecord::class;

    public function definition(): array
    {
        return [
            'event_type' => fake()->randomElement([
                'order.created',
                'order.fulfilled',
                'invoice.generated',
                'user.registered',
            ]),
            'actor_type' => fake()->randomElement(ActorType::cases())->value,
            'actor_id' => fake()->optional(0.8)->numberBetween(1, 100),
            'entity_type' => fake()->optional(0.9)->randomElement([
                'App\\Models\\Order',
                'App\\Models\\Invoice',
                'App\\Models\\User',
            ]),
            'entity_id' => fake()->optional(0.9)->uuid(),
            'payload' => ['action' => fake()->word(), 'details' => fake()->sentence()],
            'metadata' => ['ip' => fake()->ipv4(), 'user_agent' => fake()->userAgent()],
            'occurred_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function bySystem(): static
    {
        return $this->state(fn (array $attributes): array => [
            'actor_type' => ActorType::System->value,
            'actor_id' => null,
        ]);
    }

    public function byUser(int $userId): static
    {
        return $this->state(fn (array $attributes): array => [
            'actor_type' => ActorType::User->value,
            'actor_id' => $userId,
        ]);
    }
}
