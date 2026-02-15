<?php

namespace Aicl\Database\Factories;

use Aicl\Models\RlmSemanticCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RlmSemanticCache>
 */
class RlmSemanticCacheFactory extends Factory
{
    protected $model = RlmSemanticCache::class;

    public function definition(): array
    {
        $entityName = fake()->randomElement(['Invoice', 'Order', 'Project', 'Task']);
        $checkName = fake()->randomElement([
            'resource_extends_base',
            'form_uses_section',
            'model_has_fillable',
            'observer_logs_correctly',
        ]);

        return [
            'cache_key' => "{$entityName}:{$checkName}:".fake()->md5(),
            'check_name' => $checkName,
            'entity_name' => $entityName,
            'passed' => fake()->boolean(70),
            'message' => fake()->sentence(),
            'confidence' => fake()->randomFloat(2, 0.5, 1.0),
            'files_hash' => fake()->md5(),
            'expires_at' => now()->addHours(fake()->numberBetween(1, 24)),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function passing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'passed' => true,
            'confidence' => fake()->randomFloat(2, 0.8, 1.0),
        ]);
    }

    public function failing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'passed' => false,
            'confidence' => fake()->randomFloat(2, 0.5, 0.8),
        ]);
    }
}
