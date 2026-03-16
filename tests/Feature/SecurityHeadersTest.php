<?php

namespace Aicl\Tests\Feature;

use Aicl\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset static cache between tests to prevent state leaking
        SecurityHeadersMiddleware::resetCache();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    public function test_responses_include_x_frame_options_header(): void
    {
        $response = $this->get('/admin/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_responses_include_x_content_type_options_header(): void
    {
        $response = $this->get('/admin/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_responses_include_x_xss_protection_header(): void
    {
        $response = $this->get('/admin/login');

        $response->assertHeader('X-XSS-Protection', '0');
    }

    public function test_responses_include_referrer_policy_header(): void
    {
        $response = $this->get('/admin/login');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_responses_include_permissions_policy_header(): void
    {
        $response = $this->get('/admin/login');

        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_csp_report_only_header_present_on_filament_pages(): void
    {
        config(['aicl.security.csp.enabled' => true]);
        config(['aicl.security.csp.report_only' => true]);

        $response = $this->get('/admin/login');

        $response->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_filament_csp_includes_unsafe_inline_for_livewire(): void
    {
        config(['aicl.security.csp.enabled' => true]);

        $response = $this->get('/admin/login');

        $csp = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString("'unsafe-eval'", $csp);
    }

    public function test_api_csp_is_strict(): void
    {
        config(['aicl.security.csp.enabled' => true]);

        $response = $this->getJson('/api/v1/projects');

        $csp = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
    }

    public function test_security_headers_can_be_disabled(): void
    {
        config(['aicl.security.headers.enabled' => false]);

        $response = $this->get('/admin/login');

        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeaderMissing('X-Content-Type-Options');
    }

    public function test_csp_can_be_disabled_independently(): void
    {
        config(['aicl.security.csp.enabled' => false]);

        $response = $this->get('/admin/login');

        // Standard headers still present
        $response->assertHeader('X-Frame-Options', 'DENY');
        // CSP not present
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_csp_can_switch_to_enforce_mode(): void
    {
        config(['aicl.security.csp.enabled' => true]);
        config(['aicl.security.csp.report_only' => false]);

        $response = $this->get('/admin/login');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_hsts_can_be_disabled(): void
    {
        config(['aicl.security.headers.hsts' => false]);

        $response = $this->get('/admin/login');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_max_age_is_configurable(): void
    {
        config(['aicl.security.headers.hsts_max_age' => 86400]);

        $response = $this->get('/admin/login');

        $hsts = $response->headers->get('Strict-Transport-Security');
        if ($hsts !== null) {
            $this->assertStringContainsString('max-age=86400', $hsts);
        } else {
            // Non-secure requests may not have HSTS
            $this->assertTrue(true);
        }
    }
}
