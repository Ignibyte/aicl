<?php

namespace Aicl\Database\Factories;

use Aicl\Enums\KnowledgeLinkRelationship;
use Aicl\Models\KnowledgeLink;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeLink>
 */
class KnowledgeLinkFactory extends Factory
{
    protected $model = KnowledgeLink::class;

    public function definition(): array
    {
        return [
            'source_type' => (new RlmFailure)->getMorphClass(),
            'source_id' => RlmFailure::factory(),
            'target_type' => (new RlmLesson)->getMorphClass(),
            'target_id' => RlmLesson::factory(),
            'relationship' => fake()->randomElement(KnowledgeLinkRelationship::cases()),
            'confidence' => fake()->randomFloat(2, 0.3, 1.0),
        ];
    }

    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes): array => [
            'confidence' => fake()->randomFloat(2, 0.8, 1.0),
        ]);
    }

    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes): array => [
            'confidence' => fake()->randomFloat(2, 0.1, 0.3),
        ]);
    }
}
