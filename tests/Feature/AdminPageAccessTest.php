<?php

namespace Aicl\Tests\Feature;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Filament\Pages\ActivityLog;
use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\ManageSettings;
use Aicl\Filament\Pages\NotificationCenter;
use Aicl\Filament\Pages\OperationsManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminPageAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\SettingsSeeder']);

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

    // Operations Manager (formerly Queue Manager)

    public function test_operations_manager_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/operations-manager');

        $response->assertOk();
    }

    public function test_operations_manager_accessible_by_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/operations-manager');

        $response->assertOk();
    }

    public function test_operations_manager_forbidden_for_viewer(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/operations-manager');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_operations_manager_can_access_returns_false_for_null_user(): void
    {
        $this->assertFalse(OperationsManager::canAccess());
    }

    public function test_operations_manager_has_header_actions(): void
    {
        $page = new OperationsManager;
        $actions = $this->callProtectedMethod($page, 'getHeaderActions');

        $this->assertNotEmpty($actions);
    }

    // Activity Log (consolidated from LogViewer + AuditLog + DomainEventViewer + NotificationLogPage)

    public function test_activity_log_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/activity-log');

        $response->assertOk();
    }

    public function test_activity_log_forbidden_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/activity-log');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_activity_log_forbidden_for_viewer(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/activity-log');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_activity_log_can_access_returns_false_for_null_user(): void
    {
        $this->assertFalse(ActivityLog::canAccess());
    }

    public function test_activity_log_get_level_color(): void
    {
        $this->actingAs($this->superAdmin);

        $page = new ActivityLog;
        $this->assertEquals('danger', $page->getLevelColor('ERROR'));
        $this->assertEquals('warning', $page->getLevelColor('WARNING'));
        $this->assertEquals('info', $page->getLevelColor('INFO'));
    }

    public function test_activity_log_polling_interval_when_disabled(): void
    {
        $page = new ActivityLog;

        $this->assertNull($page->getPollingInterval());
    }

    public function test_activity_log_polling_interval_when_enabled(): void
    {
        $page = new ActivityLog;
        $page->liveMode = true;

        $this->assertEquals('2s', $page->getPollingInterval());
    }

    public function test_activity_log_default_properties(): void
    {
        $page = new ActivityLog;

        $this->assertNull($page->selectedFile);
        $this->assertNull($page->levelFilter);
        $this->assertNull($page->search);
        $this->assertFalse($page->liveMode);
        $this->assertEquals(100, $page->limit);
        $this->assertEquals('app-logs', $page->activeTab);
    }

    public function test_activity_log_has_header_actions(): void
    {
        $page = new ActivityLog;
        $actions = $this->callProtectedMethod($page, 'getHeaderActions');

        $this->assertNotEmpty($actions);
    }

    // Manage Settings

    public function test_settings_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/settings');

        $response->assertOk();
    }

    public function test_settings_forbidden_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/settings');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_settings_forbidden_for_viewer(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/settings');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_settings_can_access_returns_false_for_null_user(): void
    {
        $this->assertFalse(ManageSettings::canAccess());
    }

    // Notification Center

    public function test_notification_center_accessible_by_any_authenticated_user(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/notifications');

        $response->assertOk();
    }

    public function test_notification_center_redirects_guest(): void
    {
        $response = $this->get('/admin/notifications');

        $response->assertRedirect();
    }

    public function test_notification_center_default_filter(): void
    {
        $page = new NotificationCenter;

        $this->assertEquals('all', $page->filter);
    }

    public function test_notification_center_badge_returns_null_for_zero(): void
    {
        $this->actingAs($this->viewer);

        $badge = NotificationCenter::getNavigationBadge();

        $this->assertNull($badge);
    }

    public function test_notification_center_badge_color_is_danger(): void
    {
        $this->assertEquals('danger', NotificationCenter::getNavigationBadgeColor());
    }

    // Activity Log (old routes removed — notification-log, audit-log, domain-events are now tabs)

    // API Tokens

    public function test_api_tokens_accessible_when_feature_enabled(): void
    {
        config(['aicl.features.api' => true]);

        $response = $this->actingAs($this->viewer)->get('/admin/api-tokens');

        $response->assertOk();
    }

    public function test_api_tokens_returns_404_when_feature_disabled(): void
    {
        config(['aicl.features.api' => false]);

        $response = $this->actingAs($this->viewer)->get('/admin/api-tokens');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_api_tokens_redirects_guest(): void
    {
        $response = $this->get('/admin/api-tokens');

        $response->assertRedirect();
    }

    public function test_api_tokens_title(): void
    {
        $page = new ApiTokens;

        $this->assertEquals('API Tokens', $page->getTitle());
    }

    public function test_api_tokens_navigation_label(): void
    {
        $this->assertEquals('API Tokens', ApiTokens::getNavigationLabel());
    }

    public function test_api_tokens_default_properties(): void
    {
        $page = new ApiTokens;

        $this->assertEquals('', $page->newTokenName);
        $this->assertNull($page->createdToken);
    }

    public function test_api_tokens_clear_created_token(): void
    {
        $page = new ApiTokens;
        $page->createdToken = 'some-token-value';

        $page->clearCreatedToken();

        $this->assertNull($page->createdToken);
    }

    // Guest redirects for all pages

    public function test_operations_manager_redirects_guest(): void
    {
        $response = $this->get('/admin/operations-manager');

        $response->assertRedirect();
    }

    public function test_activity_log_redirects_guest(): void
    {
        $response = $this->get('/admin/activity-log');

        $response->assertRedirect();
    }

    public function test_settings_redirects_guest(): void
    {
        $response = $this->get('/admin/settings');

        $response->assertRedirect();
    }

    // Old standalone pages removed — notification-log, audit-log, domain-events routes no longer exist

    // AI Assistant standalone page removed — use the floating overlay panel (Cmd+J) instead

    protected function callProtectedMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}
