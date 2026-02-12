<?php

namespace Aicl\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiRequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_api_requests_are_logged(): void
    {
        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'API Request'
                    && $context['method'] === 'GET'
                    && str_contains($context['path'], 'api/user')
                    && array_key_exists('status', $context)
                    && array_key_exists('duration_ms', $context)
                    && array_key_exists('ip', $context);
            });

        Passport::actingAs($this->user, ['*']);

        $this->getJson('/api/user');
    }

    public function test_api_logging_can_be_disabled(): void
    {
        config(['aicl.security.api_logging' => false]);

        $this->assertFalse(config('aicl.security.api_logging'));
    }

    public function test_api_logging_includes_user_id_for_authenticated_requests(): void
    {
        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $context['user_id'] === $this->user->id;
            });

        Passport::actingAs($this->user, ['*']);

        $this->getJson('/api/user');
    }
}
