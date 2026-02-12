<?php

namespace Aicl\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use TomatoPHP\FilamentMediaManager\Traits\InteractsWithMediaManager;

class MediaGalleryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_media_manager_plugin_is_registered(): void
    {
        $panel = \Filament\Facades\Filament::getPanel('admin');
        $pluginIds = collect($panel->getPlugins())->map(fn ($p) => $p->getId())->toArray();

        $this->assertContains('filament-media-manager', $pluginIds);
    }

    public function test_media_gallery_routes_exist(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->toArray();

        $this->assertContains('admin/media', $routes);
        $this->assertContains('admin/folders', $routes);
    }

    public function test_media_gallery_page_requires_auth(): void
    {
        $response = $this->get('/admin/media');

        $response->assertRedirect();
    }

    public function test_media_gallery_page_is_a_filament_resource(): void
    {
        $resourceClass = \TomatoPHP\FilamentMediaManager\Resources\MediaResource::class;

        $this->assertTrue(class_exists($resourceClass));
        $this->assertTrue(is_subclass_of($resourceClass, \Filament\Resources\Resource::class));
    }

    public function test_folders_page_requires_auth(): void
    {
        $response = $this->get('/admin/folders');

        $response->assertRedirect();
    }

    public function test_folders_page_is_a_filament_resource(): void
    {
        $resourceClass = \TomatoPHP\FilamentMediaManager\Resources\FolderResource::class;

        $this->assertTrue(class_exists($resourceClass));
        $this->assertTrue(is_subclass_of($resourceClass, \Filament\Resources\Resource::class));
    }

    public function test_has_media_collections_trait_includes_media_manager(): void
    {
        $traits = class_uses_recursive(\Aicl\Traits\HasMediaCollections::class);

        $this->assertArrayHasKey(InteractsWithMediaManager::class, $traits);
    }

    public function test_media_serve_route_requires_authentication(): void
    {
        $response = $this->get('/media/1/test.png');

        $response->assertForbidden();
    }

    public function test_media_serve_route_returns_404_for_missing_file(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->get('/media/nonexistent/file.png');

        $response->assertNotFound();
    }

    public function test_media_disk_is_configured(): void
    {
        $disk = config('filesystems.disks.media');

        $this->assertNotNull($disk);
        $this->assertEquals('local', $disk['driver']);
        $this->assertEquals('/media', $disk['url']);
    }
}
