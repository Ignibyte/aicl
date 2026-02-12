<?php

namespace Aicl\Database\Factories;

use Aicl\Models\RlmPattern;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RlmPattern>
 */
class RlmPatternFactory extends Factory
{
    protected $model = RlmPattern::class;

    /** @var list<string> */
    private static array $targets = [
        'model', 'factory', 'migration', 'seeder', 'policy', 'observer',
        'filament', 'form', 'table', 'controller', 'test', 'exporter',
    ];

    /** @var list<string> */
    private static array $categories = [
        'structural', 'naming', 'security', 'performance', 'convention',
    ];

    /** @var list<string> */
    private static array $severities = ['error', 'warning', 'info'];

    /** @var list<string> */
    private static array $sources = ['base', 'discovered', 'manual'];

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).'_pattern',
            'description' => fake()->paragraph(),
            'target' => fake()->randomElement(self::$targets),
            'check_regex' => '/class\s+\w+.*\{/',
            'severity' => fake()->randomElement(self::$severities),
            'weight' => fake()->randomFloat(2, 0.5, 3.0),
            'category' => fake()->randomElement(self::$categories),
            'applies_when' => null,
            'source' => fake()->randomElement(self::$sources),
            'is_active' => true,
            'pass_count' => fake()->numberBetween(0, 500),
            'fail_count' => fake()->numberBetween(0, 100),
            'last_evaluated_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'owner_id' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function neverEvaluated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pass_count' => 0,
            'fail_count' => 0,
            'last_evaluated_at' => null,
        ]);
    }

    public function perfectPassRate(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pass_count' => fake()->numberBetween(10, 500),
            'fail_count' => 0,
        ]);
    }
}
