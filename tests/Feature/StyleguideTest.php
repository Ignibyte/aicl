<?php

namespace Aicl\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StyleguideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_styleguide_overview_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/styleguide-overview');

        $response->assertStatus(200);
    }

    public function test_styleguide_layout_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/layout-components');

        $response->assertStatus(200);
    }

    public function test_styleguide_metrics_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/metric-components');

        $response->assertStatus(200);
    }

    public function test_styleguide_data_display_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/data-display-components');

        $response->assertStatus(200);
    }

    public function test_styleguide_action_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/action-components');

        $response->assertStatus(200);
    }

    public function test_styleguide_interactive_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/interactive-components');

        $response->assertStatus(200);
    }

    public function test_styleguide_feedback_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/feedback-components');

        $response->assertStatus(200);
    }
}
