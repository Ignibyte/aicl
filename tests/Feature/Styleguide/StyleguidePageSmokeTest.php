<?php

namespace Aicl\Tests\Feature\Styleguide;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StyleguidePageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
    }

    public function test_overview_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/styleguide-overview');

        $response->assertOk();
        $response->assertSee('AICL Component Library');
        $response->assertSee('Total Components');
    }

    public function test_overview_page_shows_dynamic_component_counts(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/styleguide-overview');

        $response->assertOk();
        $response->assertSee('Categories');
        $response->assertSee('With JS Module');
    }

    public function test_layout_components_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/layout-components');

        $response->assertOk();
        $response->assertSee('SplitLayout');
        $response->assertSee('CardGrid');
        $response->assertSee('StatsRow');
        $response->assertSee('EmptyState');
    }

    public function test_layout_components_page_shows_ignibyte_logo(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/layout-components');

        $response->assertOk();
        $response->assertSee('IgnibyteLogo');
    }

    public function test_layout_components_page_shows_auth_split_layout(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/layout-components');

        $response->assertOk();
        $response->assertSee('AuthSplitLayout');
    }

    public function test_metric_components_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/metric-components');

        $response->assertOk();
        $response->assertSee('StatCard');
        $response->assertSee('KpiCard');
        $response->assertSee('TrendCard');
        $response->assertSee('ProgressCard');
    }

    public function test_data_display_components_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/data-display-components');

        $response->assertOk();
        $response->assertSee('MetadataList');
        $response->assertSee('InfoCard');
        $response->assertSee('StatusBadge');
        $response->assertSee('Timeline');
    }

    public function test_action_components_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/action-components');

        $response->assertOk();
        $response->assertSee('ActionBar');
        $response->assertSee('QuickAction');
        $response->assertSee('AlertBanner');
        $response->assertSee('Divider');
        $response->assertSee('Spinner');
    }

    public function test_action_components_page_shows_code_block(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/action-components');

        $response->assertOk();
        $response->assertSee('CodeBlock');
    }

    public function test_feedback_components_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/feedback-components');

        $response->assertOk();
        $response->assertSee('Toast');
        $response->assertSee('Tooltip');
        $response->assertSee('Badge');
        $response->assertSee('Avatar');
    }

    public function test_interactive_components_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/interactive-components');

        $response->assertOk();
        $response->assertSee('Modal');
        $response->assertSee('Drawer');
        $response->assertSee('Dropdown');
        $response->assertSee('Accordion');
        $response->assertSee('Combobox');
        $response->assertSee('DataTable');
        $response->assertSee('CommandPalette');
    }

    public function test_all_styleguide_pages_have_show_code_buttons(): void
    {
        $pages = [
            '/admin/layout-components',
            '/admin/metric-components',
            '/admin/data-display-components',
            '/admin/action-components',
            '/admin/feedback-components',
            '/admin/interactive-components',
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($this->admin)->get($page);
            $response->assertOk();
            $response->assertSee('Show Code', false);
        }
    }

    public function test_all_styleguide_pages_have_component_reference_blocks(): void
    {
        $pages = [
            '/admin/layout-components',
            '/admin/metric-components',
            '/admin/data-display-components',
            '/admin/action-components',
            '/admin/feedback-components',
            '/admin/interactive-components',
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($this->admin)->get($page);
            $response->assertOk();
            $response->assertSee('Component Reference', false);
        }
    }
}
