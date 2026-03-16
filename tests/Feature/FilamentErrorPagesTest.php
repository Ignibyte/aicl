<?php

namespace Aicl\Tests\Feature;

use Aicl\Filament\Pages\Errors\Forbidden;
use Aicl\Filament\Pages\Errors\NotFound;
use Aicl\Filament\Pages\Errors\ServerError;
use Aicl\Filament\Pages\Errors\ServiceUnavailable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        $this->user = User::factory()->create();
        $this->user->assignRole('super_admin');
    }

    // --- Error page classes ---

    public function test_not_found_page_has_correct_properties(): void
    {
        $page = new NotFound;

        $this->assertEquals(404, $page->code);
        $this->assertEquals('Page Not Found', $page->getTitle());
    }

    public function test_forbidden_page_has_correct_properties(): void
    {
        $page = new Forbidden;

        $this->assertEquals(403, $page->code);
        $this->assertEquals('Access Denied', $page->getTitle());
    }

    public function test_server_error_page_has_correct_properties(): void
    {
        $page = new ServerError;

        $this->assertEquals(500, $page->code);
        $this->assertEquals('Something Went Wrong', $page->getTitle());
    }

    public function test_service_unavailable_page_has_correct_properties(): void
    {
        $page = new ServiceUnavailable;

        $this->assertEquals(503, $page->code);
        $this->assertEquals('Maintenance Mode', $page->getTitle());
    }

    public function test_error_pages_are_hidden_from_navigation(): void
    {
        $this->assertFalse(NotFound::shouldRegisterNavigation());
        $this->assertFalse(Forbidden::shouldRegisterNavigation());
        $this->assertFalse(ServerError::shouldRegisterNavigation());
        $this->assertFalse(ServiceUnavailable::shouldRegisterNavigation());
    }

    public function test_error_pages_are_accessible_by_anyone(): void
    {
        $this->assertTrue(NotFound::canAccess());
        $this->assertTrue(Forbidden::canAccess());
        $this->assertTrue(ServerError::canAccess());
        $this->assertTrue(ServiceUnavailable::canAccess());
    }

    // --- Exception renderer redirects ---

    public function test_admin_404_redirects_to_error_page(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/nonexistent-page-xyz');

        $response->assertRedirect('/admin/errors/404');
    }

    public function test_admin_error_page_renders_with_sidebar(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/errors/404');

        $response->assertOk();
        $response->assertSee('Page Not Found');
        $response->assertSee('404');
    }

    public function test_admin_403_error_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/errors/403');

        $response->assertOk();
        $response->assertSee('Access Denied');
        $response->assertSee('403');
    }

    public function test_admin_500_error_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/errors/500');

        $response->assertOk();
        $response->assertSee('Something Went Wrong');
        $response->assertSee('500');
    }

    public function test_admin_503_error_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/errors/503');

        $response->assertOk();
        $response->assertSee('Maintenance Mode');
        $response->assertSee('503');
    }

    public function test_error_page_has_dashboard_link(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/errors/404');

        $response->assertOk();
        $response->assertSee('Go to Dashboard');
    }

    // --- Non-panel errors pass through ---

    public function test_api_404_returns_json_not_filament_page(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/nonexistent-endpoint');

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
    }

    public function test_non_admin_404_does_not_redirect_to_filament(): void
    {
        $response = $this->get('/nonexistent-public-page');

        $response->assertStatus(404);
        $response->assertDontSee('Go to Dashboard');
    }
}
