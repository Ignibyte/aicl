<?php

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\AiAssistant;
use Aicl\Filament\Pages\AuditLog;
use Aicl\Filament\Pages\NotificationLogPage;
use Aicl\Filament\Pages\OpsPanel;
use Aicl\Filament\Pages\RlmDashboard;
use Aicl\Filament\Widgets\CategoryBreakdownChart;
use Aicl\Filament\Widgets\FailureTrendChart;
use Aicl\Filament\Widgets\ProjectHealthWidget;
use Aicl\Filament\Widgets\PromotionQueueWidget;
use App\Models\User;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // ─── RlmDashboard ──────────────────────────────────────────

    public function test_rlm_dashboard_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(RlmDashboard::class, Page::class));
    }

    public function test_rlm_dashboard_slug(): void
    {
        $reflection = new \ReflectionClass(RlmDashboard::class);
        $prop = $reflection->getProperty('slug');

        $this->assertEquals('rlm-dashboard', $prop->getDefaultValue());
    }

    public function test_rlm_dashboard_navigation_group(): void
    {
        $reflection = new \ReflectionClass(RlmDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('RLM Hub', $defaults['navigationGroup']);
    }

    public function test_rlm_dashboard_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(RlmDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(1, $defaults['navigationSort']);
    }

    public function test_rlm_dashboard_view(): void
    {
        $reflection = new \ReflectionClass(RlmDashboard::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.rlm-dashboard', $property->getDefaultValue());
    }

    public function test_rlm_dashboard_has_header_widgets(): void
    {
        $page = new RlmDashboard;
        $reflection = new \ReflectionMethod($page, 'getHeaderWidgets');
        $reflection->setAccessible(true);
        $widgets = $reflection->invoke($page);

        $this->assertCount(2, $widgets);
        $this->assertContains(FailureTrendChart::class, $widgets);
        $this->assertContains(CategoryBreakdownChart::class, $widgets);
    }

    public function test_rlm_dashboard_has_footer_widgets(): void
    {
        $page = new RlmDashboard;
        $reflection = new \ReflectionMethod($page, 'getFooterWidgets');
        $reflection->setAccessible(true);
        $widgets = $reflection->invoke($page);

        $this->assertCount(2, $widgets);
        $this->assertContains(PromotionQueueWidget::class, $widgets);
        $this->assertContains(ProjectHealthWidget::class, $widgets);
    }

    public function test_rlm_dashboard_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(RlmDashboard::canAccess());
    }

    public function test_rlm_dashboard_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue(RlmDashboard::canAccess());
    }

    public function test_rlm_dashboard_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(RlmDashboard::canAccess());
    }

    public function test_rlm_dashboard_not_accessible_without_auth(): void
    {
        $this->assertFalse(RlmDashboard::canAccess());
    }

    // ─── AiAssistant ────────────────────────────────────────────

    public function test_ai_assistant_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(AiAssistant::class, Page::class));
    }

    public function test_ai_assistant_slug(): void
    {
        $reflection = new \ReflectionClass(AiAssistant::class);
        $prop = $reflection->getProperty('slug');

        $this->assertEquals('ai-assistant', $prop->getDefaultValue());
    }

    public function test_ai_assistant_navigation_group(): void
    {
        $reflection = new \ReflectionClass(AiAssistant::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Tools', $defaults['navigationGroup']);
    }

    public function test_ai_assistant_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(AiAssistant::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(5, $defaults['navigationSort']);
    }

    public function test_ai_assistant_view(): void
    {
        $reflection = new \ReflectionClass(AiAssistant::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.ai-assistant', $property->getDefaultValue());
    }

    public function test_ai_assistant_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(AiAssistant::canAccess());
    }

    public function test_ai_assistant_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue(AiAssistant::canAccess());
    }

    public function test_ai_assistant_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(AiAssistant::canAccess());
    }

    public function test_ai_assistant_not_accessible_without_auth(): void
    {
        $this->assertFalse(AiAssistant::canAccess());
    }

    // ─── OpsPanel ───────────────────────────────────────────────

    public function test_ops_panel_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(OpsPanel::class, Page::class));
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

        $this->assertEquals(10, $defaults['navigationSort']);
    }

    public function test_ops_panel_view(): void
    {
        $reflection = new \ReflectionClass(OpsPanel::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.ops-panel', $property->getDefaultValue());
    }

    public function test_ops_panel_has_get_service_checks_method(): void
    {
        $this->assertTrue(method_exists(OpsPanel::class, 'getServiceChecks'));
    }

    public function test_ops_panel_has_get_active_sessions_method(): void
    {
        $this->assertTrue(method_exists(OpsPanel::class, 'getActiveSessions'));
    }

    public function test_ops_panel_has_terminate_session_method(): void
    {
        $this->assertTrue(method_exists(OpsPanel::class, 'terminateSession'));
    }

    public function test_ops_panel_has_kill_session_action_method(): void
    {
        $this->assertTrue(method_exists(OpsPanel::class, 'killSessionAction'));
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

    // ─── AuditLog ───────────────────────────────────────────────

    public function test_audit_log_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(AuditLog::class, Page::class));
    }

    public function test_audit_log_implements_has_table(): void
    {
        $this->assertTrue(is_subclass_of(AuditLog::class, HasTable::class));
    }

    public function test_audit_log_implements_has_forms(): void
    {
        $this->assertTrue(is_subclass_of(AuditLog::class, HasForms::class));
    }

    public function test_audit_log_slug(): void
    {
        $reflection = new \ReflectionClass(AuditLog::class);
        $prop = $reflection->getProperty('slug');

        $this->assertEquals('audit-log', $prop->getDefaultValue());
    }

    public function test_audit_log_navigation_group(): void
    {
        $reflection = new \ReflectionClass(AuditLog::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    public function test_audit_log_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(AuditLog::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(14, $defaults['navigationSort']);
    }

    public function test_audit_log_view(): void
    {
        $reflection = new \ReflectionClass(AuditLog::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.audit-log', $property->getDefaultValue());
    }

    public function test_audit_log_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(AuditLog::canAccess());
    }

    public function test_audit_log_not_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertFalse(AuditLog::canAccess());
    }

    public function test_audit_log_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(AuditLog::canAccess());
    }

    public function test_audit_log_not_accessible_without_auth(): void
    {
        $this->assertFalse(AuditLog::canAccess());
    }

    // ─── NotificationLogPage ────────────────────────────────────

    public function test_notification_log_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(NotificationLogPage::class, Page::class));
    }

    public function test_notification_log_implements_has_table(): void
    {
        $this->assertTrue(is_subclass_of(NotificationLogPage::class, HasTable::class));
    }

    public function test_notification_log_implements_has_forms(): void
    {
        $this->assertTrue(is_subclass_of(NotificationLogPage::class, HasForms::class));
    }

    public function test_notification_log_slug(): void
    {
        $reflection = new \ReflectionClass(NotificationLogPage::class);
        $prop = $reflection->getProperty('slug');

        $this->assertEquals('notification-log', $prop->getDefaultValue());
    }

    public function test_notification_log_navigation_group(): void
    {
        $reflection = new \ReflectionClass(NotificationLogPage::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    public function test_notification_log_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(NotificationLogPage::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(15, $defaults['navigationSort']);
    }

    public function test_notification_log_view(): void
    {
        $reflection = new \ReflectionClass(NotificationLogPage::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.notification-log', $property->getDefaultValue());
    }

    public function test_notification_log_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(NotificationLogPage::canAccess());
    }

    public function test_notification_log_not_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertFalse(NotificationLogPage::canAccess());
    }

    public function test_notification_log_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(NotificationLogPage::canAccess());
    }

    public function test_notification_log_not_accessible_without_auth(): void
    {
        $this->assertFalse(NotificationLogPage::canAccess());
    }
}
