<?php

namespace Aicl\Tests\Unit\Misc;

use Aicl\Livewire\ActivityFeed;
use Livewire\Component;
use Livewire\WithPagination;
use PHPUnit\Framework\TestCase;

class ActivityFeedTest extends TestCase
{
    public function test_extends_livewire_component(): void
    {
        $this->assertTrue((new \ReflectionClass(ActivityFeed::class))->isSubclassOf(Component::class));
    }

    public function test_uses_with_pagination(): void
    {
        $uses = class_uses(ActivityFeed::class);
        $this->assertContains(WithPagination::class, $uses);
    }

    public function test_default_per_page(): void
    {
        $feed = new ActivityFeed;
        $this->assertEquals(10, $feed->perPage);
    }

    public function test_default_poll_interval(): void
    {
        $feed = new ActivityFeed;
        $this->assertEquals(30, $feed->pollInterval);
    }

    public function test_default_heading(): void
    {
        $feed = new ActivityFeed;
        $this->assertEquals('Recent Activity', $feed->heading);
    }

    public function test_default_show_causer(): void
    {
        $feed = new ActivityFeed;
        $this->assertTrue($feed->showCauser);
    }

    public function test_default_show_subject(): void
    {
        $feed = new ActivityFeed;
        $this->assertTrue($feed->showSubject);
    }

    public function test_default_filters_are_null(): void
    {
        $feed = new ActivityFeed;

        $this->assertNull($feed->subjectType);
        $this->assertNull($feed->subjectId);
        $this->assertNull($feed->causerType);
        $this->assertNull($feed->causerId);
        $this->assertNull($feed->logName);
    }

    public function test_has_activities_computed_property(): void
    {
        $this->assertTrue((new \ReflectionClass(ActivityFeed::class))->hasMethod('activities'));
    }

    public function test_has_render_method(): void
    {
        $this->assertTrue((new \ReflectionClass(ActivityFeed::class))->hasMethod('render'));

        $reflection = new \ReflectionMethod(ActivityFeed::class, 'render');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        /** @phpstan-ignore-next-line */
        $this->assertEquals('Illuminate\Contracts\View\View', $returnType->getName());
    }
}
