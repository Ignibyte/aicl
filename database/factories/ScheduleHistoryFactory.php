<?php

namespace Aicl\Database\Factories;

use Aicl\Models\ScheduleHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleHistory>
 */
class ScheduleHistoryFactory extends Factory
{
    protected $model = ScheduleHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $started = $this->faker->dateTimeBetween('-7 days', 'now');
        $durationMs = $this->faker->numberBetween(50, 30000);

        return [
            'command' => $this->faker->randomElement([
                'backup:run',
                'backup:clean',
                'backup:monitor',
                'aicl:horizon:snapshot',
                'schedule:prune-history',
                'queue:prune-batches',
                'telescope:prune',
                'inspire',
            ]),
            'description' => $this->faker->optional(0.5)->sentence(3),
            'expression' => $this->faker->randomElement([
                '* * * * *',
                '*/5 * * * *',
                '0 * * * *',
                '0 2 * * *',
                '0 3 * * *',
                '0 8 * * *',
            ]),
            'status' => 'success',
            'exit_code' => 0,
            'output' => null,
            'duration_ms' => $durationMs,
            'started_at' => $started,
            'finished_at' => (clone $started)->modify("+{$durationMs} milliseconds"),
            'created_at' => $started,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => 'running',
            'exit_code' => null,
            'duration_ms' => null,
            'finished_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'exit_code' => 1,
            'output' => $this->faker->sentence(),
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn () => [
            'status' => 'skipped',
            'exit_code' => null,
            'duration_ms' => 0,
            'output' => null,
        ]);
    }
}
