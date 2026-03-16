<?php

namespace Aicl\Tests\Feature\Health;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Filament\Pages\OpsPanel;
use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceCheckResult;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OpsPanelPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('viewer');
    }

    // ── Access Control ───────────────────────────────────────

    public function test_ops_panel_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/ops-panel');

        $response->assertOk();
    }

    public function test_ops_panel_accessible_by_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/ops-panel');

        $response->assertOk();
    }

    public function test_ops_panel_forbidden_for_viewer(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/ops-panel');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_ops_panel_redirects_guest(): void
    {
        $response = $this->get('/admin/ops-panel');

        $response->assertRedirect();
    }

    public function test_can_access_returns_false_for_null_user(): void
    {
        $this->assertFalse(OpsPanel::canAccess());
    }

    public function test_can_access_returns_true_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin);

        $this->assertTrue(OpsPanel::canAccess());
    }

    public function test_can_access_returns_true_for_admin(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(OpsPanel::canAccess());
    }

    public function test_can_access_returns_false_for_viewer(): void
    {
        $this->actingAs($this->viewer);

        $this->assertFalse(OpsPanel::canAccess());
    }

    // ── Page Properties ──────────────────────────────────────

    public function test_page_extends_filament_page(): void
    {
        $this->assertTrue(is_subclass_of(OpsPanel::class, Page::class));
    }

    public function test_page_slug(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('ops-panel', $defaults['slug']);
    }

    public function test_page_navigation_group(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('System', $defaults['navigationGroup']);
    }

    public function test_page_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame(5, $defaults['navigationSort']);
    }

    public function test_page_title(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('Ops Panel', $defaults['title']);
    }

    public function test_page_navigation_label(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('Ops Panel', $defaults['navigationLabel']);
    }

    // ── getServiceChecks() ───────────────────────────────────

    public function test_get_service_checks_returns_array_of_results(): void
    {
        $this->actingAs($this->superAdmin);

        $page = new OpsPanel;
        $results = $page->getServiceChecks();

        $this->assertIsArray($results);

        foreach ($results as $result) {
            $this->assertInstanceOf(ServiceCheckResult::class, $result);
        }
    }

    public function test_get_service_checks_delegates_to_registry(): void
    {
        $this->actingAs($this->superAdmin);

        $expectedResults = [
            ServiceCheckResult::healthy('TestA', 'heroicon-o-check-circle'),
            ServiceCheckResult::healthy('TestB', 'heroicon-o-check-circle'),
        ];

        $registry = $this->mock(HealthCheckRegistry::class);
        $registry->shouldReceive('runAllCached')->once()->andReturn($expectedResults);

        $page = new OpsPanel;
        $results = $page->getServiceChecks();

        $this->assertSame($expectedResults, $results);
    }

    // ── Header Actions ───────────────────────────────────────

    public function test_has_refresh_header_action(): void
    {
        $page = new OpsPanel;

        $method = new \ReflectionMethod(OpsPanel::class, 'getHeaderActions');
        $method->setAccessible(true);
        $actions = $method->invoke($page);

        $this->assertNotEmpty($actions);
        $this->assertSame('forceRefresh', $actions[0]->getName());
    }
}
