<?php

namespace Aicl\Database\Factories;

use Aicl\Enums\ResolutionMethod;
use Aicl\Models\FailureReport;
use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailureReport>
 */
class FailureReportFactory extends Factory
{
    protected $model = FailureReport::class;

    public function definition(): array
    {
        $phases = ['Phase 1: Plan', 'Phase 2: Design', 'Phase 3: Generate', 'Phase 4: Validate', 'Phase 5: Register'];
        $agents = ['/architect', '/solutions', '/tester', '/rlm', '/designer', '/pm'];

        return [
            'rlm_failure_id' => RlmFailure::factory(),
            'project_hash' => fake()->sha256(),
            'entity_name' => fake()->randomElement(['Invoice', 'Project', 'Task', 'Contact', 'Report']),
            'scaffolder_args' => null,
            'phase' => fake()->randomElement($phases),
            'agent' => fake()->randomElement($agents),
            'resolved' => false,
            'resolution_notes' => null,
            'resolution_method' => null,
            'time_to_resolve' => null,
            'reported_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'resolved_at' => null,
            'is_active' => true,
            'owner_id' => User::factory(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'resolved' => true,
            'resolution_notes' => fake()->paragraph(),
            'resolution_method' => fake()->randomElement(ResolutionMethod::cases()),
            'time_to_resolve' => fake()->numberBetween(5, 480),
            'resolved_at' => fake()->dateTimeBetween($attributes['reported_at'] ?? '-3 months', 'now'),
        ]);
    }

    public function unresolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'resolved' => false,
            'resolution_notes' => null,
            'resolution_method' => null,
            'time_to_resolve' => null,
            'resolved_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function withScaffolderArgs(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scaffolder_args' => [
                'fields' => 'name:string,status:enum:TaskStatus',
                'states' => 'draft,active,completed',
                'widgets' => true,
            ],
        ]);
    }
}
