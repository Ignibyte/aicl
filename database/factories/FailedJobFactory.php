<?php

namespace Aicl\Database\Factories;

use Aicl\Models\FailedJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FailedJob>
 */
class FailedJobFactory extends Factory
{
    protected $model = FailedJob::class;

    public function definition(): array
    {
        $jobClass = fake()->randomElement([
            'App\\Jobs\\ProcessInvoice',
            'App\\Jobs\\SendReport',
            'App\\Jobs\\SyncData',
        ]);

        return [
            'uuid' => Str::uuid()->toString(),
            'connection' => 'redis',
            'queue' => fake()->randomElement(['default', 'high', 'low']),
            'payload' => [
                'displayName' => $jobClass,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['commandName' => $jobClass],
            ],
            'exception' => fake()->randomElement([
                'RuntimeException: Connection timed out in /app/Jobs/ProcessInvoice.php:42',
                'InvalidArgumentException: Missing required field in /app/Jobs/SendReport.php:28',
                'PDOException: SQLSTATE[HY000]: General error in /app/Jobs/SyncData.php:55',
            ]),
            'failed_at' => now(),
        ];
    }
}
