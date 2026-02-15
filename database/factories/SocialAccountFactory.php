<?php

namespace Aicl\Database\Factories;

use Aicl\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['google', 'github', 'azure']),
            'provider_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'avatar_url' => fake()->optional(0.7)->imageUrl(),
            'token' => fake()->sha256(),
            'refresh_token' => fake()->optional(0.5)->sha256(),
            'token_expires_at' => fake()->optional(0.8)->dateTimeBetween('now', '+1 year'),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'token_expires_at' => now()->subDay(),
        ]);
    }
}
