<?php

namespace Aicl\Database\Factories;

use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreventionRule>
 */
class PreventionRuleFactory extends Factory
{
    protected $model = PreventionRule::class;

    public function definition(): array
    {
        return [
            'rlm_failure_id' => fake()->optional(0.7)->passthrough(RlmFailure::factory()),
            'trigger_context' => fake()->randomElement([
                ['has_states' => true],
                ['has_enums' => true, 'field_types' => ['enum']],
                ['has_uuid' => true],
                ['has_relationships' => true, 'relationship_types' => ['BelongsTo']],
                ['field_types' => ['foreignId', 'json']],
            ]),
            'rule_text' => fake()->randomElement([
                'When generating entities with states, always verify state transition definitions match the design blueprint.',
                'Override searchableColumns() when the model lacks a "name" or "title" field.',
                'Use Filament\Schemas\Components\Section, NOT Filament\Forms\Components\Section.',
                'For UUID primary keys, use $table->uuid("id")->primary() and HasUuids trait.',
                'Ensure observer log messages reference actual model attributes, not assumed "name" field.',
                'Validate that factory states produce data within the expected ranges.',
                'PostgreSQL LIKE is case-sensitive — use LOWER(column) LIKE for case-insensitive search.',
            ]),
            'confidence' => fake()->randomFloat(2, 0.3, 1.0),
            'priority' => fake()->numberBetween(0, 10),
            'is_active' => true,
            'applied_count' => fake()->numberBetween(0, 50),
            'last_applied_at' => fake()->optional(0.6)->dateTimeBetween('-3 months'),
            'owner_id' => User::factory(),
        ];
    }

    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes): array => [
            'confidence' => fake()->randomFloat(2, 0.8, 1.0),
            'applied_count' => fake()->numberBetween(10, 100),
        ]);
    }

    public function withoutFailure(): static
    {
        return $this->state(fn (array $attributes): array => [
            'rlm_failure_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
