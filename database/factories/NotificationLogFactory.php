<?php

namespace Aicl\Database\Factories;

use Aicl\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement([
                'App\\Notifications\\WelcomeNotification',
                'App\\Notifications\\OrderShipped',
                'App\\Notifications\\InvoiceGenerated',
            ]),
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => User::factory(),
            'sender_type' => fake()->optional(0.5)->passthrough('App\\Models\\User'),
            'sender_id' => null,
            'channels' => fake()->randomElements(['mail', 'database', 'broadcast'], fake()->numberBetween(1, 3)),
            'channel_status' => ['mail' => 'sent'],
            'data' => ['message' => fake()->sentence()],
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel_status' => ['mail' => 'failed', 'database' => 'sent'],
        ]);
    }
}
