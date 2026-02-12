<?php

namespace Aicl\Tests\Unit\Filament\Resources;

use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Aicl\Models\FailedJob;
use Filament\Resources\Resource;
use PHPUnit\Framework\TestCase;

class FailedJobResourceTest extends TestCase
{
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
}
