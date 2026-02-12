<?php

namespace Aicl\Tests\Unit\Policies;

use Aicl\Policies\BasePolicy;
use PHPUnit\Framework\TestCase;

class BasePolicyTest extends TestCase
{
    public function test_base_policy_has_all_required_methods(): void
    {
        $reflection = new \ReflectionClass(BasePolicy::class);

        $expectedMethods = [
            'viewAny', 'view', 'create', 'update', 'delete',
            'restore', 'forceDelete', 'restoreAny', 'forceDeleteAny',
            'replicate', 'reorder',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "BasePolicy is missing method: {$method}"
            );
        }
    }

    public function test_base_policy_is_abstract(): void
    {
        $reflection = new \ReflectionClass(BasePolicy::class);

        $this->assertTrue($reflection->isAbstract());
    }

    public function test_permission_prefix_is_abstract(): void
    {
        $reflection = new \ReflectionClass(BasePolicy::class);
        $method = $reflection->getMethod('permissionPrefix');

        $this->assertTrue($method->isAbstract());
    }

    public function test_base_policy_uses_handles_authorization(): void
    {
        $traits = class_uses(BasePolicy::class);

        $this->assertContains(
            \Illuminate\Auth\Access\HandlesAuthorization::class,
            $traits
        );
    }

    public function test_viewany_method_signature(): void
    {
        $reflection = new \ReflectionClass(BasePolicy::class);
        $method = $reflection->getMethod('viewAny');

        $this->assertCount(1, $method->getParameters());
        $this->assertEquals('user', $method->getParameters()[0]->getName());
    }

    public function test_view_method_requires_model_parameter(): void
    {
        $reflection = new \ReflectionClass(BasePolicy::class);
        $method = $reflection->getMethod('view');

        $this->assertCount(2, $method->getParameters());
        $this->assertEquals('user', $method->getParameters()[0]->getName());
        $this->assertEquals('record', $method->getParameters()[1]->getName());
    }
}
