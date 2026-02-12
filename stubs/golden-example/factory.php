<?php

// PATTERN: Factory covers ALL fillable fields with realistic fake data.
// PATTERN: Use fake() helper (not $this->faker).
// PATTERN: Provide states for every status/variation.

namespace Aicl\Database\Factories;

use Aicl\Enums\ProjectPriority;
use Aicl\States\Active;
use Aicl\States\Archived;
use Aicl\States\Completed;
use Aicl\States\Draft;
use Aicl\States\OnHold;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * PATTERN: Generic type annotation for the factory.
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    // PATTERN: Explicit model binding when factory is in a non-standard namespace.
    protected $model = Project::class;

    public function definition(): array
    {
        // PATTERN: Generate related dates that make logical sense.
        $startDate = fake()->dateTimeBetween('-6 months', '+1 month');
        $endDate = fake()->dateTimeBetween($startDate, '+12 months');

        return [
            'name' => fake()->company().' '.fake()->jobTitle(),
            'description' => fake()->paragraph(3),
            // PATTERN: Default state uses the state class (not a string).
            'status' => Draft::class,
            // PATTERN: Randomly pick from enum cases.
            'priority' => fake()->randomElement(ProjectPriority::cases()),
            'start_date' => $startDate,
            'end_date' => $endDate,
            // PATTERN: optional() makes some records have null values.
            'budget' => fake()->optional(0.7)->randomFloat(2, 1000, 500000),
            'is_active' => true,
            // PATTERN: Related models use their factory.
            'owner_id' => User::factory(),
        ];
    }

    // PATTERN: One state method per status.
    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => Draft::class]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => Active::class]);
    }

    public function onHold(): static
    {
        return $this->state(fn (): array => ['status' => OnHold::class]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => Completed::class]);
    }

    // PATTERN: Archived state also sets is_active to false for logical consistency.
    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => Archived::class,
            'is_active' => false,
        ]);
    }

    // PATTERN: Priority states for testing priority-based queries.
    public function highPriority(): static
    {
        return $this->state(fn (): array => ['priority' => ProjectPriority::High]);
    }

    public function critical(): static
    {
        return $this->state(fn (): array => ['priority' => ProjectPriority::Critical]);
    }

    // PATTERN: Compound states for specific test scenarios.
    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => Active::class,
            'end_date' => fake()->dateTimeBetween('-2 months', '-1 day'),
        ]);
    }
}
