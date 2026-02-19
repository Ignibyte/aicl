<?php

namespace Aicl\Database\Factories;

use Aicl\Models\EntityWaiver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntityWaiver>
 */
class EntityWaiverFactory extends Factory
{
    protected $model = EntityWaiver::class;

    public function definition(): array
    {
        return [
            'entity_name' => fake()->word(),
            'pattern_id' => 'model.'.fake()->randomElement(['namespace', 'extends', 'fillable', 'casts_method']),
            'reason' => fake()->sentence(),
            'scope_justification' => fake()->paragraph(),
            'ticket_url' => fake()->optional(0.5)->url(),
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('+1 week', '+6 months'),
            'created_by' => 1,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function permanent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => null,
        ]);
    }
}
