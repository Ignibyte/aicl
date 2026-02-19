<?php

namespace Aicl\Database\Factories;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Models\RlmFailure;
use Aicl\States\RlmFailure\Confirmed;
use Aicl\States\RlmFailure\Deprecated;
use Aicl\States\RlmFailure\Investigating;
use Aicl\States\RlmFailure\Reported;
use Aicl\States\RlmFailure\Resolved;
use Aicl\States\RlmFailure\WontFix;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RlmFailure>
 */
class RlmFailureFactory extends Factory
{
    protected $model = RlmFailure::class;

    public function definition(): array
    {
        $reportCount = fake()->numberBetween(1, 50);
        $resolutionCount = fake()->numberBetween(0, $reportCount);

        return [
            'failure_code' => 'F-'.fake()->unique()->numerify('###'),
            'pattern_id' => null,
            'category' => fake()->randomElement(FailureCategory::cases()),
            'subcategory' => fake()->optional(0.5)->word(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'attempt' => fake()->optional(0.4)->paragraph(),
            'feedback' => fake()->optional(0.4)->paragraph(),
            'root_cause' => fake()->optional(0.7)->paragraph(),
            'fix' => fake()->optional(0.6)->paragraph(),
            'preventive_rule' => fake()->optional(0.4)->sentence(),
            'severity' => fake()->randomElement(FailureSeverity::cases()),
            'entity_context' => null,
            'scaffolding_fixed' => false,
            'first_seen_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'last_seen_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'report_count' => $reportCount,
            'project_count' => fake()->numberBetween(1, min($reportCount, 10)),
            'resolution_count' => $resolutionCount,
            'resolution_rate' => $reportCount > 0 ? round($resolutionCount / $reportCount, 3) : null,
            'promoted_to_base' => false,
            'promoted_at' => null,
            'aicl_version' => fake()->optional(0.8)->semver(),
            'laravel_version' => fake()->optional(0.8)->semver(),
            'validator_layer' => fake()->optional(0.3)->randomElement(['L1', 'L2', 'PHPSTAN', 'OTHER']),
            'validator_id' => fake()->optional(0.3)->regexify('[A-Z]{1,2}-\d{3}'),
            'entity_name' => fake()->optional(0.3)->word(),
            'phase' => fake()->optional(0.3)->randomElement(['phase-3', 'phase-4', 'phase-5', 'phase-6']),
            'is_active' => true,
            'owner_id' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function reported(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Reported::getMorphClass(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Confirmed::getMorphClass(),
        ]);
    }

    public function investigating(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Investigating::getMorphClass(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Resolved::getMorphClass(),
        ]);
    }

    public function wontFix(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WontFix::getMorphClass(),
        ]);
    }

    public function deprecated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Deprecated::getMorphClass(),
        ]);
    }

    public function promotable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'report_count' => fake()->numberBetween(3, 50),
            'project_count' => fake()->numberBetween(2, 10),
            'promoted_to_base' => false,
        ]);
    }

    public function promoted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'promoted_to_base' => true,
            'promoted_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ]);
    }

    public function scaffoldingFixed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scaffolding_fixed' => true,
        ]);
    }

    public function structured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attempt' => fake()->paragraph(),
            'feedback' => fake()->paragraph(),
            'fix' => fake()->paragraph(),
            'preventive_rule' => fake()->sentence(),
            'validator_layer' => fake()->randomElement(['L1', 'L2', 'PHPSTAN']),
            'validator_id' => fake()->regexify('[A-Z]{1,2}-\d{3}'),
            'entity_name' => fake()->word(),
            'phase' => fake()->randomElement(['phase-3', 'phase-4', 'phase-5', 'phase-6']),
        ]);
    }
}
