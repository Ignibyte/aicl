<?php

namespace Aicl\Database\Factories;

use Aicl\Models\DistilledLesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistilledLesson>
 */
class DistilledLessonFactory extends Factory
{
    protected $model = DistilledLesson::class;

    public function definition(): array
    {
        $agents = ['architect', 'tester', 'rlm', 'solutions', 'designer', 'pm'];
        $agent = fake()->randomElement($agents);

        $phases = match ($agent) {
            'architect' => [3, 5],
            'tester' => [4, 6, 7],
            'rlm' => [4, 6],
            'designer' => [3],
            'solutions' => [2],
            'pm' => [1, 7, 8],
            default => [3],
        };

        return [
            'lesson_code' => 'DL-'.fake()->unique()->numberBetween(1, 999),
            'title' => fake()->sentence(),
            'guidance' => fake()->paragraphs(2, true),
            'target_agent' => $agent,
            'target_phase' => fake()->randomElement($phases),
            'trigger_context' => fake()->optional(0.6)->passthrough(['has_states' => true]),
            'source_failure_codes' => [sprintf('BF-%03d', fake()->numberBetween(1, 15))],
            'source_lesson_ids' => null,
            'impact_score' => fake()->randomFloat(2, 1.0, 50.0),
            'confidence' => fake()->randomFloat(2, 0.5, 1.0),
            'applied_count' => 0,
            'prevented_count' => 0,
            'ignored_count' => 0,
            'is_active' => true,
            'last_distilled_at' => now(),
            'generation' => 1,
            'owner_id' => User::factory(),
        ];
    }

    public function forAgent(string $agent): static
    {
        return $this->state(fn (array $attributes): array => [
            'target_agent' => $agent,
        ]);
    }

    public function highImpact(): static
    {
        return $this->state(fn (array $attributes): array => [
            'impact_score' => fake()->randomFloat(2, 20.0, 50.0),
            'confidence' => fake()->randomFloat(2, 0.8, 1.0),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
