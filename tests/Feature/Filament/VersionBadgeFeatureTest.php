<?php

namespace Aicl\Tests\Feature\Filament;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VersionBadgeFeatureTest extends TestCase
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

    public function test_version_badge_renders_in_topbar(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('fi-topbar-version-badge', false);
    }

    public function test_version_badge_shows_version_number(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertOk();
        // Version should match semver pattern
        $this->assertMatchesRegularExpression('/v\d+\.\d+\.\d+/', $response->getContent());
    }

    public function test_version_badge_links_to_changelog(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('changelog', false);
    }
}
