<?php

namespace Aicl\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cors_config_exists(): void
    {
        $this->assertNotNull(config('cors'));
    }

    public function test_cors_allowed_methods_are_restricted(): void
    {
        $methods = config('cors.allowed_methods');

        $this->assertIsArray($methods);
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('PUT', $methods);
        $this->assertContains('DELETE', $methods);
        $this->assertContains('OPTIONS', $methods);
    }

    public function test_cors_allowed_headers_are_restricted(): void
    {
        $headers = config('cors.allowed_headers');

        $this->assertIsArray($headers);
        $this->assertContains('Content-Type', $headers);
        $this->assertContains('Authorization', $headers);
        $this->assertContains('Accept', $headers);
        // No wildcard '*' in allowed headers
        $this->assertNotContains('*', $headers);
    }

    public function test_cors_supports_credentials(): void
    {
        $this->assertTrue(config('cors.supports_credentials'));
    }

    public function test_cors_max_age_is_set(): void
    {
        $this->assertEquals(86400, config('cors.max_age'));
    }

    public function test_cors_exposes_rate_limit_headers(): void
    {
        $exposed = config('cors.exposed_headers');

        $this->assertIsArray($exposed);
        $this->assertContains('X-RateLimit-Limit', $exposed);
        $this->assertContains('X-RateLimit-Remaining', $exposed);
        $this->assertContains('Retry-After', $exposed);
    }

    public function test_cors_origins_default_to_app_url(): void
    {
        $origins = config('cors.allowed_origins');

        $this->assertIsArray($origins);
        $this->assertNotEmpty($origins);
    }

    public function test_cors_paths_cover_api(): void
    {
        $paths = config('cors.paths');

        $this->assertIsArray($paths);
        $this->assertContains('api/*', $paths);
    }
}
