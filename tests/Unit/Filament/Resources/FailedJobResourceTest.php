<?php

namespace Aicl\Tests\Unit\Filament\Resources;

use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Aicl\Models\FailedJob;
use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailedJobResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    public function test_extends_resource(): void
    {
        $this->assertTrue(is_subclass_of(FailedJobResource::class, Resource::class));
    }

    public function test_model_is_failed_job(): void
    {
        $reflection = new \ReflectionClass(FailedJobResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(FailedJob::class, $defaults['model']);
    }

    public function test_slug(): void
    {
        $reflection = new \ReflectionClass(FailedJobResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('failed-jobs', $defaults['slug']);
    }

    public function test_navigation_group(): void
    {
        $reflection = new \ReflectionClass(FailedJobResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    public function test_navigation_icon_is_null(): void
    {
        $reflection = new \ReflectionClass(FailedJobResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertNull($defaults['navigationIcon']);
    }

    public function test_cannot_create(): void
    {
        $this->assertFalse(FailedJobResource::canCreate());
    }

    public function test_defines_get_pages(): void
    {
        $pages = FailedJobResource::getPages();

        $this->assertIsArray($pages);
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('view', $pages);
    }

    public function test_can_access_returns_false_for_guest(): void
    {
        $this->assertFalse(FailedJobResource::canAccess());
    }

    public function test_can_access_returns_true_for_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $this->assertTrue(FailedJobResource::canAccess());
    }

    public function test_can_access_returns_true_for_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue(FailedJobResource::canAccess());
    }

    public function test_can_access_returns_false_for_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->assertFalse(FailedJobResource::canAccess());
    }

    public function test_can_access_returns_false_for_editor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('editor');
        $this->actingAs($user);

        $this->assertFalse(FailedJobResource::canAccess());
    }
}
