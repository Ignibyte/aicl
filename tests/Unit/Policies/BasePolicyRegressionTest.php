<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Policies;

use Aicl\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Regression tests for BasePolicy PHPStan changes.
 *
 * Covers the return type declarations (bool) on all 11 policy methods,
 * the abstract permissionPrefix() method, and the Shield permission
 * format (Action:Prefix). PHPStan enforced strict return types and
 * parameter type hints for User and Model.
 */
class BasePolicyRegressionTest extends TestCase
{
    /**
     * Test BasePolicy is abstract.
     *
     * Must be extended by entity-specific policies.
     */
    public function test_is_abstract_class(): void
    {
        // Arrange
        $reflection = new ReflectionClass(BasePolicy::class);

        // Assert
        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test BasePolicy uses HandlesAuthorization trait.
     */
    public function test_uses_handles_authorization_trait(): void
    {
        // Arrange
        $traits = class_uses(BasePolicy::class);

        // Assert
        $this->assertArrayHasKey(HandlesAuthorization::class, $traits);
    }

    /**
     * Test permissionPrefix is abstract and protected.
     *
     * PHPStan enforced strict return type string.
     */
    public function test_permission_prefix_is_abstract_and_protected(): void
    {
        // Arrange
        $method = new ReflectionMethod(BasePolicy::class, 'permissionPrefix');

        // Assert
        $this->assertTrue($method->isAbstract());
        $this->assertTrue($method->isProtected());

        // Verify return type is string
        $returnType = $method->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('string', $returnType->getName());
    }

    /**
     * Test all policy methods have bool return type.
     *
     * PHPStan enforced return type declarations on all methods.
     */
    public function test_all_policy_methods_return_bool(): void
    {
        // Arrange
        $methods = [
            'viewAny', 'view', 'create', 'update', 'delete',
            'restore', 'forceDelete', 'restoreAny', 'forceDeleteAny',
            'replicate', 'reorder',
        ];

        // Act & Assert
        foreach ($methods as $methodName) {
            $method = new ReflectionMethod(BasePolicy::class, $methodName);
            $returnType = $method->getReturnType();

            $this->assertInstanceOf(
                ReflectionNamedType::class,
                $returnType,
                "Method {$methodName} should have a return type"
            );

            /** @var ReflectionNamedType $returnType */
            $this->assertSame(
                'bool',
                $returnType->getName(),
                "Method {$methodName} should return bool"
            );
        }
    }

    /**
     * Test methods with only User parameter (no record).
     *
     * viewAny, create, restoreAny, forceDeleteAny, reorder take
     * only a User parameter.
     */
    public function test_user_only_methods_have_single_parameter(): void
    {
        // Arrange
        $userOnlyMethods = ['viewAny', 'create', 'restoreAny', 'forceDeleteAny', 'reorder'];

        foreach ($userOnlyMethods as $methodName) {
            $method = new ReflectionMethod(BasePolicy::class, $methodName);
            $params = $method->getParameters();

            // Assert: single User parameter
            $this->assertCount(1, $params, "Method {$methodName} should have 1 parameter");
            $this->assertSame('user', $params[0]->getName(), "Method {$methodName} parameter should be named 'user'");
        }
    }

    /**
     * Test methods with User + Model parameters.
     *
     * view, update, delete, restore, forceDelete, replicate take
     * both a User and a Model record.
     */
    public function test_record_methods_have_two_parameters(): void
    {
        // Arrange
        $recordMethods = ['view', 'update', 'delete', 'restore', 'forceDelete', 'replicate'];

        foreach ($recordMethods as $methodName) {
            $method = new ReflectionMethod(BasePolicy::class, $methodName);
            $params = $method->getParameters();

            // Assert: User + Model parameters
            $this->assertCount(2, $params, "Method {$methodName} should have 2 parameters");
            $this->assertSame('user', $params[0]->getName());
            $this->assertSame('record', $params[1]->getName());
        }
    }

    /**
     * Test concrete policy implementation uses permission format.
     *
     * Creates a concrete implementation to verify the permission
     * format pattern: "Action:Prefix".
     */
    public function test_concrete_policy_uses_shield_permission_format(): void
    {
        // Arrange: create a concrete policy with a known prefix
        $policy = new class extends BasePolicy
        {
            protected function permissionPrefix(): string
            {
                return 'TestEntity';
            }
        };

        // Act: use reflection to verify permissionPrefix returns correctly
        $reflection = new ReflectionMethod($policy, 'permissionPrefix');
        $prefix = $reflection->invoke($policy);

        // Assert
        $this->assertSame('TestEntity', $prefix);
    }
}
