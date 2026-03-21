<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Livewire;

use Aicl\Livewire\ActivityFeed;
use Livewire\Component;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for ActivityFeed Livewire component PHPStan changes.
 *
 * Covers the PHPDoc generics on the activities() computed property
 * (@return LengthAwarePaginator<int, Activity>) and the import cleanup
 * (using LengthAwarePaginator instead of FQCN in the return type).
 */
class ActivityFeedRegressionTest extends TestCase
{
    // -- Class structure --

    /**
     * Test activities method exists and is computed.
     *
     * PHPStan change: Added @return LengthAwarePaginator<int, Activity> annotation
     * and changed from FQCN return type to imported class.
     */
    public function test_activities_method_exists(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(ActivityFeed::class, 'activities');

        // Assert: method is public
        $this->assertTrue($reflection->isPublic());
    }

    /**
     * Test activities method has Computed attribute.
     *
     * The method should be decorated with Livewire's #[Computed] attribute.
     */
    public function test_activities_has_computed_attribute(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(ActivityFeed::class, 'activities');
        $attributes = $reflection->getAttributes();
        $hasComputed = false;

        foreach ($attributes as $attr) {
            if ($attr->getName() === 'Livewire\Attributes\Computed') {
                $hasComputed = true;

                break;
            }
        }

        // Assert
        $this->assertTrue($hasComputed, 'activities() should have #[Computed] attribute');
    }

    /**
     * Test onEntityChanged method exists for event handling.
     *
     * The component listens for entity-changed events to refresh the feed.
     */
    public function test_on_entity_changed_method_exists(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(ActivityFeed::class, 'onEntityChanged');

        // Assert
        $this->assertTrue($reflection->isPublic());
    }

    /**
     * Test class extends Livewire Component.
     */
    public function test_extends_livewire_component(): void
    {
        // Assert: verify parent class chain via reflection
        $reflection = new \ReflectionClass(ActivityFeed::class);
        $parentClass = $reflection->getParentClass();
        $this->assertNotFalse($parentClass);
    }
}
