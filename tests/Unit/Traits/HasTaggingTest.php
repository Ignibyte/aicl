<?php

namespace Aicl\Tests\Unit\Traits;

use Aicl\Traits\HasTagging;
use PHPUnit\Framework\TestCase;
use Spatie\Tags\HasTags;

class HasTaggingTest extends TestCase
{
    public function test_trait_exists(): void
    {
        $this->assertTrue(trait_exists(HasTagging::class));
    }

    public function test_trait_uses_spatie_has_tags(): void
    {
        $traits = class_uses(HasTagging::class);

        $this->assertContains(HasTags::class, $traits);
    }

    public function test_trait_provides_tag_methods(): void
    {
        // HasTagging wraps HasTags, which provides these methods on models
        $traitMethods = get_class_methods(HasTags::class) ?: [];
        $reflection = new \ReflectionClass(HasTags::class);
        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $expectedMethods = ['attachTag', 'attachTags', 'detachTag', 'syncTags'];

        foreach ($expectedMethods as $method) {
            $this->assertContains($method, $methods, "HasTags should provide {$method} method");
        }
    }

    public function test_trait_provides_scope_methods(): void
    {
        $reflection = new \ReflectionClass(HasTags::class);
        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('scopeWithAnyTags', $methods);
        $this->assertContains('scopeWithAllTags', $methods);
    }
}
