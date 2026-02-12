<?php

namespace Aicl\Tests\Unit\Traits;

use Aicl\Traits\HasMediaCollections;
use PHPUnit\Framework\TestCase;

class HasMediaCollectionsTest extends TestCase
{
    public function test_trait_exists(): void
    {
        $this->assertTrue(trait_exists(HasMediaCollections::class));
    }

    public function test_trait_uses_interacts_with_media(): void
    {
        $traits = class_uses(HasMediaCollections::class);

        $this->assertContains(
            \Spatie\MediaLibrary\InteractsWithMedia::class,
            $traits,
        );
    }

    public function test_trait_defines_register_media_collections(): void
    {
        $this->assertTrue(
            method_exists(HasMediaCollections::class, 'registerMediaCollections'),
        );
    }

    public function test_trait_defines_register_media_conversions(): void
    {
        $this->assertTrue(
            method_exists(HasMediaCollections::class, 'registerMediaConversions'),
        );
    }
}
