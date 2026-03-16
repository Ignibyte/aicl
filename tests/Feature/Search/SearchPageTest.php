<?php

namespace Aicl\Tests\Feature\Search;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\\Database\\Seeders\\RoleSeeder']);

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

    public function test_search_page_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/search');

        $response->assertOk();
    }

    public function test_search_page_accessible_by_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/search');

        $response->assertOk();
    }

    public function test_search_page_accessible_by_viewer(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/search');

        $response->assertOk();
    }

    public function test_search_page_redirects_guest(): void
    {
        $response = $this->get('/admin/search');

        $response->assertRedirect();
    }

    public function test_search_page_accepts_query_parameter(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/search?q=test');

        $response->assertOk();
    }

    public function test_search_page_accepts_type_parameter(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/search?q=test&type=User');

        $response->assertOk();
    }

    public function test_search_page_shows_not_configured_when_disabled(): void
    {
        config(['aicl.search.enabled' => false]);

        $response = $this->actingAs($this->superAdmin)->get('/admin/search');

        $response->assertOk();
        $response->assertSee('Search Not Configured');
    }
}
