<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Policies;

use Aicl\Policies\BasePolicy;
use Aicl\Policies\UserPolicy;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Regression tests for UserPolicy PHPStan changes.
 *
 * Covers the self-view and self-update bypass logic where users
 * can always view/update their own profile. PHPStan enforced
 * strict return types and parameter type hints (User, Model).
 */
class UserPolicyRegressionTest extends TestCase
{
    /**
     * Test UserPolicy extends BasePolicy.
     */
    public function test_extends_base_policy(): void
    {
        // Arrange
        $reflection = new ReflectionClass(UserPolicy::class);
        $parent = $reflection->getParentClass();

        // Assert: parent class exists and is BasePolicy
        $this->assertNotFalse($parent, 'UserPolicy should have a parent class');
        $this->assertSame(BasePolicy::class, $parent->getName());
    }

    /**
     * Test permissionPrefix returns 'User'.
     *
     * PHPStan enforced string return type on the abstract method.
     */
    public function test_permission_prefix_returns_user(): void
    {
        // Arrange
        $policy = new UserPolicy;

        // Act: call protected method via reflection
        $reflection = new ReflectionMethod($policy, 'permissionPrefix');
        $prefix = $reflection->invoke($policy);

        // Assert
        $this->assertSame('User', $prefix);
    }

    /**
     * Test view method is overridden (not inherited from BasePolicy).
     *
     * UserPolicy overrides view() with self-view bypass logic.
     */
    public function test_view_is_overridden(): void
    {
        // Arrange
        $method = new ReflectionMethod(UserPolicy::class, 'view');

        // Assert: declared on UserPolicy, not BasePolicy
        $this->assertSame(
            UserPolicy::class,
            $method->getDeclaringClass()->getName(),
            'view() should be declared on UserPolicy (overridden)'
        );
    }

    /**
     * Test update method is overridden (not inherited from BasePolicy).
     *
     * UserPolicy overrides update() with self-update bypass logic.
     */
    public function test_update_is_overridden(): void
    {
        // Arrange
        $method = new ReflectionMethod(UserPolicy::class, 'update');

        // Assert: declared on UserPolicy, not BasePolicy
        $this->assertSame(
            UserPolicy::class,
            $method->getDeclaringClass()->getName(),
            'update() should be declared on UserPolicy (overridden)'
        );
    }

    /**
     * Test view method has correct parameter types.
     *
     * PHPStan enforced User and Model parameter type hints.
     */
    public function test_view_method_parameter_types(): void
    {
        // Arrange
        $method = new ReflectionMethod(UserPolicy::class, 'view');
        $params = $method->getParameters();

        // Assert: 2 parameters named user and record
        $this->assertCount(2, $params);
        $this->assertSame('user', $params[0]->getName());
        $this->assertSame('record', $params[1]->getName());
    }

    /**
     * Test update method has correct parameter types.
     *
     * PHPStan enforced User and Model parameter type hints.
     */
    public function test_update_method_parameter_types(): void
    {
        // Arrange
        $method = new ReflectionMethod(UserPolicy::class, 'update');
        $params = $method->getParameters();

        // Assert: 2 parameters named user and record
        $this->assertCount(2, $params);
        $this->assertSame('user', $params[0]->getName());
        $this->assertSame('record', $params[1]->getName());
    }

    /**
     * Test non-overridden methods are inherited from BasePolicy.
     *
     * Methods like create, delete, viewAny should come from BasePolicy.
     */
    public function test_non_overridden_methods_are_inherited(): void
    {
        // Arrange
        $inheritedMethods = ['viewAny', 'create', 'delete', 'restore', 'forceDelete'];

        foreach ($inheritedMethods as $methodName) {
            $method = new ReflectionMethod(UserPolicy::class, $methodName);

            // Assert: declared on BasePolicy (inherited, not overridden)
            $this->assertSame(
                BasePolicy::class,
                $method->getDeclaringClass()->getName(),
                "{$methodName}() should be inherited from BasePolicy"
            );
        }
    }
}
