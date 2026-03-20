<?php

namespace Aicl\Database\Factories\Horizon;

use Aicl\Horizon\Models\QueueMetricSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueMetricSnapshot>
 */
class QueueMetricSnapshotFactory extends Factory
{
    protected $model = QueueMetricSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['queue', 'job']),
            'name' => $this->faker->randomElement(['default', 'high', 'low', 'App\\Jobs\\ProcessPayment', 'App\\Jobs\\SendNotification']),
            'throughput' => $this->faker->randomFloat(2, 0, 500),
            'runtime' => $this->faker->randomFloat(2, 1, 5000),
            'wait' => $this->faker->optional(0.5)->randomFloat(2, 0, 120),
            'recorded_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Set the snapshot as a queue type with wait time.
     */
    public function queue(?string $name = null): static
    {
        return $this->state(fn () => [
            'type' => 'queue',
            'name' => $name ?? $this->faker->randomElement(['default', 'high', 'low']),
            'wait' => $this->faker->randomFloat(2, 0.1, 120),
        ]);
    }

    /**
     * Set the snapshot as a job type without wait time.
     */
    public function job(?string $name = null): static
    {
        return $this->state(fn () => [
            'type' => 'job',
            'name' => $name ?? $this->faker->randomElement([
                'App\\Jobs\\ProcessPayment',
                'App\\Jobs\\SendNotification',
                'App\\Jobs\\GenerateReport',
            ]),
            'wait' => null,
        ]);
    }

    /**
     * Set the recorded_at to a specific time.
     */
    public function recordedAt(\DateTimeInterface $dateTime): static
    {
        return $this->state(fn () => [
            'recorded_at' => $dateTime,
        ]);
    }
}
