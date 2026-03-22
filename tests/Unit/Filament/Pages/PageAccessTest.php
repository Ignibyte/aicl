<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\ActivityLog;
use Aicl\Filament\Pages\OpsPanel;
use App\Models\User;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests page access control, slugs, views, and navigation
 * configuration for OpsPanel and ActivityLog Filament pages.
 */
class PageAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // ─── OpsPanel ───────────────────────────────────────────────

    public function test_ops_panel_extends_page(): void
    {
        $this->assertTrue((new \ReflectionClass(OpsPanel::class))->isSubclassOf(Page::class));
    }

    public function test_ops_panel_slug(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $prop = $reflection->getProperty('slug');

        $this->assertEquals('ops-panel', $prop->getDefaultValue());
    }

    public function test_ops_panel_navigation_group(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    public function test_ops_panel_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(5, $defaults['navigationSort']);
    }

    public function test_ops_panel_view(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.ops-panel', $property->getDefaultValue());
    }

    public function test_ops_panel_has_get_service_checks_method(): void
    {
        $this->assertTrue((new \ReflectionClass(OpsPanel::class))->hasMethod('getServiceChecks'));
    }

    public function test_ops_panel_no_longer_has_session_methods(): void
    {
        $this->assertFalse(method_exists(OpsPanel::class, 'getActiveSessions'));
        $this->assertFalse(method_exists(OpsPanel::class, 'terminateSession'));
        $this->assertFalse(method_exists(OpsPanel::class, 'killSessionAction'));
    }

    public function test_ops_panel_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(OpsPanel::canAccess());
    }

    public function test_ops_panel_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue(OpsPanel::canAccess());
    }

    public function test_ops_panel_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(OpsPanel::canAccess());
    }

    public function test_ops_panel_not_accessible_without_auth(): void
    {
        $this->assertFalse(OpsPanel::canAccess());
    }

    // ─── ActivityLog ───────────────────────────────────────────

    public function test_activity_log_extends_page(): void
    {
        $this->assertTrue((new \ReflectionClass(ActivityLog::class))->isSubclassOf(Page::class));
    }

    public function test_activity_log_implements_has_forms(): void
    {
        $this->assertTrue((new \ReflectionClass(ActivityLog::class))->isSubclassOf(HasForms::class));
    }

    public function test_activity_log_slug(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $prop = $reflection->getProperty('slug');

        $this->assertEquals('activity-log', $prop->getDefaultValue());
    }

    public function test_activity_log_navigation_group(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    public function test_activity_log_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(7, $defaults['navigationSort']);
    }

    public function test_activity_log_view(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.activity-log', $property->getDefaultValue());
    }

    public function test_activity_log_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(ActivityLog::canAccess());
    }

    public function test_activity_log_not_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertFalse(ActivityLog::canAccess());
    }

    public function test_activity_log_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(ActivityLog::canAccess());
    }

    public function test_activity_log_not_accessible_without_auth(): void
    {
        $this->assertFalse(ActivityLog::canAccess());
    }
}
