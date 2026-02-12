<?php

namespace Aicl\Database\Factories;

use Aicl\Enums\ScoreType;
use Aicl\Models\RlmScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RlmScore>
 */
class RlmScoreFactory extends Factory
{
    protected $model = RlmScore::class;

    public function definition(): array
    {
        $total = fake()->randomElement([40, 42, 8]);
        $passed = fake()->numberBetween((int) ($total * 0.7), $total);

        return [
            'entity_name' => fake()->word(),
            'score_type' => fake()->randomElement(ScoreType::cases()),
            'passed' => $passed,
            'total' => $total,
            'percentage' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
            'errors' => fake()->numberBetween(0, $total - $passed),
            'warnings' => fake()->numberBetween(0, 5),
            'details' => null,
            'owner_id' => User::factory(),
        ];
    }

    public function structural(): static
    {
        return $this->state(fn (array $attributes): array => [
            'score_type' => ScoreType::Structural,
            'total' => 42,
        ]);
    }

    public function semantic(): static
    {
        return $this->state(fn (array $attributes): array => [
            'score_type' => ScoreType::Semantic,
            'total' => 8,
        ]);
    }

    public function combined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'score_type' => ScoreType::Combined,
        ]);
    }

    public function perfect(): static
    {
        return $this->state(fn (array $attributes): array => [
            'passed' => $attributes['total'] ?? 42,
            'percentage' => 100.00,
            'errors' => 0,
            'warnings' => 0,
        ]);
    }

    public function forEntity(string $entityName): static
    {
        return $this->state(fn (array $attributes): array => [
            'entity_name' => $entityName,
        ]);
    }

    public function withDetails(): static
    {
        return $this->state(fn (array $attributes): array => [
            'details' => [
                ['pattern' => 'model.fillable', 'passed' => true],
                ['pattern' => 'model.soft_deletes', 'passed' => true],
                ['pattern' => 'model.uuid', 'passed' => false, 'error' => 'Missing HasUuids trait'],
            ],
        ]);
    }
}
