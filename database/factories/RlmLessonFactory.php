<?php

namespace Aicl\Database\Factories;

use Aicl\Models\RlmLesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RlmLesson>
 */
class RlmLessonFactory extends Factory
{
    protected $model = RlmLesson::class;

    public function definition(): array
    {
        $topics = ['DDEV', 'Testing', 'Filament', 'PostgreSQL', 'Eloquent', 'Octane', 'Packages', 'Scaffolder'];
        $contextTags = ['entity', 'entity:states', 'entity:uuid', 'service', 'filament:form', 'filament:table', 'testing:factory', 'command'];

        return [
            'topic' => fake()->randomElement($topics),
            'subtopic' => fake()->optional(0.5)->word(),
            'summary' => fake()->sentence(),
            'detail' => fake()->paragraphs(2, true),
            'tags' => fake()->optional(0.7)->words(3, true),
            'context_tags' => fake()->optional(0.6)->randomElements($contextTags, fake()->numberBetween(1, 3)),
            'source' => fake()->optional(0.5)->randomElement(['failures.md', 'base-failures.md', 'lessons-learned.md', 'agent-discovery']),
            'confidence' => fake()->randomFloat(2, 0.5, 1.0),
            'is_verified' => false,
            'view_count' => 0,
            'is_active' => true,
            'owner_id' => User::factory(),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_verified' => true,
            'confidence' => fake()->randomFloat(2, 0.8, 1.0),
        ]);
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes): array => [
            'view_count' => fake()->numberBetween(50, 500),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
