<?php

namespace Aicl\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_rate_limiter_is_defined(): void
    {
        $this->assertTrue(RateLimiter::limiter('api') !== null);
    }

    public function test_api_public_rate_limiter_is_defined(): void
    {
        $this->assertTrue(RateLimiter::limiter('api-public') !== null);
    }

    public function test_api_heavy_rate_limiter_is_defined(): void
    {
        $this->assertTrue(RateLimiter::limiter('api-heavy') !== null);
    }
}
