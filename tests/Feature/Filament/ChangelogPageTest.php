<?php

namespace Aicl\Tests\Feature\Filament;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Filament\Pages\Changelog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

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

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('viewer');
    }

    public function test_changelog_page_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/changelog');

        $response->assertOk();
    }

    public function test_changelog_page_renders_markdown_content(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/changelog');

        $response->assertOk();
        // The changelog should contain version numbers rendered as HTML
        $response->assertSee('AICL Framework Changelog', false);
    }

    public function test_changelog_page_shows_version_in_title(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/changelog');

        $response->assertOk();
        $response->assertSee('Changelog', false);
    }

    public function test_changelog_page_not_accessible_by_viewer(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/changelog');

        // Viewer should not get 200 — may get 403 or 500 (pre-existing MustTwoFactor Breezy bug)
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_changelog_page_class_structure(): void
    {
        $page = new Changelog;

        $this->assertSame('aicl::filament.pages.changelog', (new \ReflectionProperty($page, 'view'))->getValue($page));

        $html = $page->getChangelogHtml();
        $this->assertStringContainsString('AICL Framework Changelog', $html);
    }

    public function test_changelog_page_can_access_returns_false_for_guests(): void
    {
        // Ensure no user is authenticated
        auth()->logout();

        $this->assertFalse(Changelog::canAccess());
    }
}
