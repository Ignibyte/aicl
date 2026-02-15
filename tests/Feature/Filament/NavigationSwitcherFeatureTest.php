<?php

namespace Aicl\Tests\Feature\Filament;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NavigationSwitcherFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

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

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
    }

    public function test_switchable_mode_renders_early_init_script(): void
    {
        config(['aicl.theme.navigation_layout' => 'switchable']);

        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('aicl_nav_layout', false);
        $response->assertSee('data-nav-mode', false);
    }

    public function test_switchable_mode_renders_toggle_button(): void
    {
        config(['aicl.theme.navigation_layout' => 'switchable']);

        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('navigationSwitcher()', false);
        $response->assertSee('fi-topbar-nav-switcher', false);
    }

    public function test_sidebar_mode_does_not_render_toggle(): void
    {
        config(['aicl.theme.navigation_layout' => 'sidebar']);

        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertOk();
        $response->assertDontSee('fi-topbar-nav-switcher', false);
        $response->assertDontSee('navigationSwitcher()', false);
    }

    public function test_topbar_mode_does_not_render_toggle(): void
    {
        config(['aicl.theme.navigation_layout' => 'topbar']);

        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertOk();
        $response->assertDontSee('fi-topbar-nav-switcher', false);
        $response->assertDontSee('navigationSwitcher()', false);
    }
}
