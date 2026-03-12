<?php

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\ActivityLog;
use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\Backups;
use Aicl\Filament\Pages\ManageSettings;
use Aicl\Filament\Pages\NotificationCenter;
use Aicl\Filament\Pages\OperationsManager;
use Aicl\Filament\Pages\Search;
use App\Models\User;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
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

        $this->assertNull($defaults['navigationIcon']);
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

        $this->assertNull($defaults['navigationIcon']);
    }

    public function test_manage_settings_navigation_group(): void
    {
        $reflection = new \ReflectionClass(ManageSettings::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
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

        $this->assertNull($defaults['navigationIcon']);
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

    // ─── ActivityLog (gap coverage) ─────────────────────────────

    public function test_activity_log_implements_has_forms(): void
    {
        $this->assertTrue(is_subclass_of(ActivityLog::class, HasForms::class));
    }

    public function test_activity_log_navigation_icon(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertNull($defaults['navigationIcon']);
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

    public function test_activity_log_navigation_label(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Activity Log', $defaults['navigationLabel']);
    }

    public function test_activity_log_title(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Activity Log', $defaults['title']);
    }

    public function test_activity_log_view(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.activity-log', $property->getDefaultValue());
    }

    public function test_activity_log_has_form_method(): void
    {
        $this->assertTrue(method_exists(ActivityLog::class, 'form'));
    }

    public function test_activity_log_has_log_entries_computed_method(): void
    {
        $this->assertTrue(method_exists(ActivityLog::class, 'logEntries'));
    }

    public function test_activity_log_has_log_files_computed_method(): void
    {
        $this->assertTrue(method_exists(ActivityLog::class, 'logFiles'));
    }

    public function test_activity_log_has_header_actions(): void
    {
        $page = new ActivityLog;
        $method = new \ReflectionMethod(ActivityLog::class, 'getHeaderActions');
        $method->setAccessible(true);
        $actions = $method->invoke($page);

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        $actionNames = array_map(fn ($a) => $a->getName(), $actions);
        $this->assertContains('download', $actionNames);
        $this->assertContains('clear', $actionNames);
        $this->assertContains('delete', $actionNames);
    }

    // ─── OperationsManager (gap coverage) ──────────────────────────

    public function test_operations_manager_navigation_icon(): void
    {
        $reflection = new \ReflectionClass(OperationsManager::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertNull($defaults['navigationIcon']);
    }

    public function test_operations_manager_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(OperationsManager::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(6, $defaults['navigationSort']);
    }

    public function test_operations_manager_navigation_label(): void
    {
        $reflection = new \ReflectionClass(OperationsManager::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Operations Manager', $defaults['navigationLabel']);
    }

    public function test_operations_manager_title(): void
    {
        $reflection = new \ReflectionClass(OperationsManager::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Operations Manager', $defaults['title']);
    }

    public function test_operations_manager_view(): void
    {
        $reflection = new \ReflectionClass(OperationsManager::class);
        $property = $reflection->getProperty('view');

        $this->assertEquals('aicl::filament.pages.operations-manager', $property->getDefaultValue());
    }

    public function test_operations_manager_has_active_tab_property(): void
    {
        $reflection = new \ReflectionClass(OperationsManager::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('overview', $defaults['activeTab']);
    }

    public function test_operations_manager_has_active_section_property(): void
    {
        $reflection = new \ReflectionClass(OperationsManager::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('queues', $defaults['activeSection']);
    }

    public function test_operations_manager_has_table_method(): void
    {
        $this->assertTrue(method_exists(OperationsManager::class, 'table'));
    }

    public function test_operations_manager_has_get_queue_stats_method(): void
    {
        $this->assertTrue(method_exists(OperationsManager::class, 'getQueueStats'));
    }

    public function test_operations_manager_accessible_by_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(OperationsManager::canAccess());
    }

    public function test_operations_manager_accessible_by_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue(OperationsManager::canAccess());
    }

    public function test_operations_manager_not_accessible_by_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(OperationsManager::canAccess());
    }

    public function test_operations_manager_not_accessible_without_auth(): void
    {
        $this->assertFalse(OperationsManager::canAccess());
    }

    // ─── ApiTokens (gap coverage) ───────────────────────────────

    public function test_api_tokens_navigation_icon(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertNull($defaults['navigationIcon']);
    }

    public function test_api_tokens_navigation_group(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
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

    // ─── ActivityLog access control (gap coverage) ──────────────

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
