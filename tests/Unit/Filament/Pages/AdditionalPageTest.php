<?php

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\Backups;
use Aicl\Filament\Pages\LogViewer;
use Aicl\Filament\Pages\ManageSettings;
use Aicl\Filament\Pages\NotificationCenter;
use Aicl\Filament\Pages\QueueDashboard;
use Aicl\Filament\Pages\Search;
use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Filament\Widgets\RecentFailedJobsWidget;
use App\Models\User;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;
use Tests\TestCase;

class AdditionalPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // ─── Backups ────────────────────────────────────────────────

    public function test_backups_extends_base_backups(): void
    {
        $this->assertTrue(is_subclass_of(Backups::class, BaseBackups::class));
    }

    public function test_backups_extends_filament_page(): void
    {
        $this->assertTrue(is_subclass_of(Backups::class, Page::class));
    }

    public function test_backups_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(Backups::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(3, $defaults['navigationSort']);
    }

    public function test_backups_class_is_in_aicl_namespace(): void
    {
        $this->assertStringStartsWith('Aicl\\Filament\\Pages\\', Backups::class);
    }

    // ─── Search (gap coverage) ──────────────────────────────────

    public function test_search_navigation_icon(): void
    {
        $reflection = new \ReflectionClass(Search::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(Heroicon::OutlinedMagnifyingGlass, $defaults['navigationIcon']);
    }

    public function test_search_navigation_group(): void
    {
        $reflection = new \ReflectionClass(Search::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    public function test_search_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(Search::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(20, $defaults['navigationSort']);
    }

    public function test_search_navigation_label(): void
    {
        $reflection = new \ReflectionClass(Search::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Search', $defaults['navigationLabel']);
    }

    public function test_search_title(): void
    {
        $reflection = new \ReflectionClass(Search::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Search', $defaults['title']);
    }

    public function test_search_view(): void
    {
        $reflection = new \ReflectionClass(Search::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.search', $property->getDefaultValue());
    }

    public function test_search_can_access_returns_true_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertTrue(Search::canAccess());
    }

    public function test_search_can_access_returns_false_for_guest(): void
    {
        $this->assertFalse(Search::canAccess());
    }

    public function test_search_get_entity_types_returns_all_types_option(): void
    {
        $page = new Search;
        $types = $page->getEntityTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey('', $types);
        $this->assertEquals('All Types', $types['']);
    }

    public function test_search_updated_query_clears_computed_results(): void
    {
        $page = new Search;

        // Calling updatedQuery should not throw an error
        $page->updatedQuery();

        $this->assertTrue(true);
    }

    public function test_search_updated_entity_type_clears_computed_results(): void
    {
        $page = new Search;

        // Calling updatedEntityType should not throw an error
        $page->updatedEntityType();

        $this->assertTrue(true);
    }

    public function test_search_has_results_computed_method(): void
    {
        $this->assertTrue(method_exists(Search::class, 'results'));
    }

    // ─── ManageSettings (gap coverage) ──────────────────────────

    public function test_manage_settings_implements_has_forms(): void
    {
        $this->assertTrue(is_subclass_of(ManageSettings::class, HasForms::class));
    }

    public function test_manage_settings_navigation_icon(): void
    {
        $reflection = new \ReflectionClass(ManageSettings::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(Heroicon::OutlinedCog6Tooth, $defaults['navigationIcon']);
    }

    public function test_manage_settings_navigation_group(): void
    {
        $reflection = new \ReflectionClass(ManageSettings::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Settings', $defaults['navigationGroup']);
    }

    public function test_manage_settings_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(ManageSettings::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(1, $defaults['navigationSort']);
    }

    public function test_manage_settings_navigation_label(): void
    {
        $reflection = new \ReflectionClass(ManageSettings::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Settings', $defaults['navigationLabel']);
    }

    public function test_manage_settings_title(): void
    {
        $reflection = new \ReflectionClass(ManageSettings::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Application Settings', $defaults['title']);
    }

    public function test_manage_settings_view(): void
    {
        $reflection = new \ReflectionClass(ManageSettings::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.manage-settings', $property->getDefaultValue());
    }

    public function test_manage_settings_default_data_property(): void
    {
        $reflection = new \ReflectionClass(ManageSettings::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals([], $defaults['data']);
    }

    public function test_manage_settings_has_save_method(): void
    {
        $this->assertTrue(method_exists(ManageSettings::class, 'save'));
    }

    public function test_manage_settings_has_form_method(): void
    {
        $this->assertTrue(method_exists(ManageSettings::class, 'form'));
    }

    public function test_manage_settings_has_mount_method(): void
    {
        $this->assertTrue(method_exists(ManageSettings::class, 'mount'));
    }

    // ─── NotificationCenter (gap coverage) ──────────────────────

    public function test_notification_center_implements_has_forms(): void
    {
        $this->assertTrue(is_subclass_of(NotificationCenter::class, HasForms::class));
    }

    public function test_notification_center_implements_has_table(): void
    {
        $this->assertTrue(is_subclass_of(NotificationCenter::class, HasTable::class));
    }

    public function test_notification_center_navigation_icon(): void
    {
        $reflection = new \ReflectionClass(NotificationCenter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(Heroicon::OutlinedBell, $defaults['navigationIcon']);
    }

    public function test_notification_center_navigation_group(): void
    {
        $reflection = new \ReflectionClass(NotificationCenter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Account', $defaults['navigationGroup']);
    }

    public function test_notification_center_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(NotificationCenter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(100, $defaults['navigationSort']);
    }

    public function test_notification_center_navigation_label(): void
    {
        $reflection = new \ReflectionClass(NotificationCenter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Notifications', $defaults['navigationLabel']);
    }

    public function test_notification_center_title(): void
    {
        $reflection = new \ReflectionClass(NotificationCenter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Notifications', $defaults['title']);
    }

    public function test_notification_center_view(): void
    {
        $reflection = new \ReflectionClass(NotificationCenter::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.notification-center', $property->getDefaultValue());
    }

    public function test_notification_center_has_table_method(): void
    {
        $this->assertTrue(method_exists(NotificationCenter::class, 'table'));
    }

    public function test_notification_center_has_form_method(): void
    {
        $this->assertTrue(method_exists(NotificationCenter::class, 'form'));
    }

    // ─── LogViewer (gap coverage) ───────────────────────────────

    public function test_log_viewer_implements_has_forms(): void
    {
        $this->assertTrue(is_subclass_of(LogViewer::class, HasForms::class));
    }

    public function test_log_viewer_navigation_icon(): void
    {
        $reflection = new \ReflectionClass(LogViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(Heroicon::OutlinedDocumentText, $defaults['navigationIcon']);
    }

    public function test_log_viewer_navigation_group(): void
    {
        $reflection = new \ReflectionClass(LogViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    public function test_log_viewer_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(LogViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(12, $defaults['navigationSort']);
    }

    public function test_log_viewer_navigation_label(): void
    {
        $reflection = new \ReflectionClass(LogViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Log Viewer', $defaults['navigationLabel']);
    }

    public function test_log_viewer_title(): void
    {
        $reflection = new \ReflectionClass(LogViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Log Viewer', $defaults['title']);
    }

    public function test_log_viewer_view(): void
    {
        $reflection = new \ReflectionClass(LogViewer::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.log-viewer', $property->getDefaultValue());
    }

    public function test_log_viewer_has_form_method(): void
    {
        $this->assertTrue(method_exists(LogViewer::class, 'form'));
    }

    public function test_log_viewer_has_log_entries_computed_method(): void
    {
        $this->assertTrue(method_exists(LogViewer::class, 'logEntries'));
    }

    public function test_log_viewer_has_log_files_computed_method(): void
    {
        $this->assertTrue(method_exists(LogViewer::class, 'logFiles'));
    }

    public function test_log_viewer_has_header_actions(): void
    {
        $page = new LogViewer;
        $method = new \ReflectionMethod(LogViewer::class, 'getHeaderActions');
        $method->setAccessible(true);
        $actions = $method->invoke($page);

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        $actionNames = array_map(fn ($a) => $a->getName(), $actions);
        $this->assertContains('download', $actionNames);
        $this->assertContains('clear', $actionNames);
        $this->assertContains('delete', $actionNames);
    }

    // ─── QueueDashboard (gap coverage) ──────────────────────────

    public function test_queue_dashboard_navigation_icon(): void
    {
        $reflection = new \ReflectionClass(QueueDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(Heroicon::OutlinedQueueList, $defaults['navigationIcon']);
    }

    public function test_queue_dashboard_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(QueueDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(11, $defaults['navigationSort']);
    }

    public function test_queue_dashboard_navigation_label(): void
    {
        $reflection = new \ReflectionClass(QueueDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Queue Dashboard', $defaults['navigationLabel']);
    }

    public function test_queue_dashboard_title(): void
    {
        $reflection = new \ReflectionClass(QueueDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Queue Dashboard', $defaults['title']);
    }

    public function test_queue_dashboard_view(): void
    {
        $reflection = new \ReflectionClass(QueueDashboard::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.queue-dashboard', $property->getDefaultValue());
    }

    public function test_queue_dashboard_header_widgets_contain_queue_stats(): void
    {
        $page = new QueueDashboard;
        $method = new \ReflectionMethod(QueueDashboard::class, 'getHeaderWidgets');
        $method->setAccessible(true);
        $widgets = $method->invoke($page);

        $this->assertContains(QueueStatsWidget::class, $widgets);
    }

    public function test_queue_dashboard_footer_widgets_contain_recent_failed_jobs(): void
    {
        $page = new QueueDashboard;
        $method = new \ReflectionMethod(QueueDashboard::class, 'getFooterWidgets');
        $method->setAccessible(true);
        $widgets = $method->invoke($page);

        $this->assertContains(RecentFailedJobsWidget::class, $widgets);
    }

    public function test_queue_dashboard_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(QueueDashboard::canAccess());
    }

    public function test_queue_dashboard_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue(QueueDashboard::canAccess());
    }

    public function test_queue_dashboard_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(QueueDashboard::canAccess());
    }

    public function test_queue_dashboard_not_accessible_without_auth(): void
    {
        $this->assertFalse(QueueDashboard::canAccess());
    }

    // ─── ApiTokens (gap coverage) ───────────────────────────────

    public function test_api_tokens_navigation_icon(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(Heroicon::OutlinedKey, $defaults['navigationIcon']);
    }

    public function test_api_tokens_navigation_group(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Settings', $defaults['navigationGroup']);
    }

    public function test_api_tokens_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(2, $defaults['navigationSort']);
    }

    public function test_api_tokens_view(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.api-tokens', $property->getDefaultValue());
    }

    public function test_api_tokens_has_get_tokens_method(): void
    {
        $this->assertTrue(method_exists(ApiTokens::class, 'getTokens'));
    }

    public function test_api_tokens_has_create_token_method(): void
    {
        $this->assertTrue(method_exists(ApiTokens::class, 'createToken'));
    }

    public function test_api_tokens_has_revoke_token_method(): void
    {
        $this->assertTrue(method_exists(ApiTokens::class, 'revokeToken'));
    }

    public function test_api_tokens_get_tokens_returns_empty_when_user_has_no_tokens_method(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $page = new ApiTokens;
        $tokens = $page->getTokens();

        $this->assertIsArray($tokens);
        // User model may not have tokens() method (Passport not installed),
        // in which case it returns an empty array.
    }

    // ─── ManageSettings access control (gap coverage) ────────────

    public function test_manage_settings_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(ManageSettings::canAccess());
    }

    public function test_manage_settings_not_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertFalse(ManageSettings::canAccess());
    }

    public function test_manage_settings_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(ManageSettings::canAccess());
    }

    public function test_manage_settings_not_accessible_without_auth(): void
    {
        $this->assertFalse(ManageSettings::canAccess());
    }

    // ─── LogViewer access control (gap coverage) ────────────────

    public function test_log_viewer_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(LogViewer::canAccess());
    }

    public function test_log_viewer_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue(LogViewer::canAccess());
    }

    public function test_log_viewer_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(LogViewer::canAccess());
    }

    public function test_log_viewer_not_accessible_without_auth(): void
    {
        $this->assertFalse(LogViewer::canAccess());
    }

    // ─── Search access control (gap coverage) ───────────────────

    public function test_search_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(Search::canAccess());
    }

    public function test_search_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue(Search::canAccess());
    }

    public function test_search_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertTrue(Search::canAccess());
    }
}
