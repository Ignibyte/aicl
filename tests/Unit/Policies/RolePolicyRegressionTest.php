<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Policies;

use Aicl\Policies\RolePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Regression tests for RolePolicy PHPStan changes.
 *
 * Covers declare(strict_types=1) addition, AuthUser parameter type
 * hints, bool return types, and Shield permission format for Role
 * model. RolePolicy does NOT extend BasePolicy because Spatie's Role
 * model is not compatible with BasePolicy's type hints.
 */
class RolePolicyRegressionTest extends TestCase
{
    /**
     * Test RolePolicy does NOT extend BasePolicy.
     *
     * Spatie's Role model requires different type hints, so RolePolicy
     * stands alone with HandlesAuthorization trait.
     */
    public function test_does_not_extend_base_policy(): void
    {
        // Arrange
        $reflection = new ReflectionClass(RolePolicy::class);
        $parent = $reflection->getParentClass();

        // Assert: no parent class (direct class, not extending BasePolicy)
        $this->assertFalse($parent);
    }

    /**
     * Test uses HandlesAuthorization trait.
     */
    public function test_uses_handles_authorization_trait(): void
    {
        // Arrange
        $traits = class_uses(RolePolicy::class);

        // Assert
        $this->assertArrayHasKey(HandlesAuthorization::class, $traits);
    }

    /**
     * Test all policy methods return bool.
     *
     * PHPStan enforced return type declarations on all methods.
     */
    public function test_all_methods_return_bool(): void
    {
        // Arrange
        $methods = [
            'viewAny', 'view', 'create', 'update', 'delete',
            'restore', 'forceDelete', 'forceDeleteAny', 'restoreAny',
            'replicate', 'reorder',
        ];

        foreach ($methods as $methodName) {
            $method = new ReflectionMethod(RolePolicy::class, $methodName);
            $returnType = $method->getReturnType();

            // Assert
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
     * Test viewAny method has single AuthUser parameter.
     *
     * PHPStan enforced the AuthUser type hint.
     */
    public function test_view_any_has_auth_user_parameter(): void
    {
        // Arrange
        $method = new ReflectionMethod(RolePolicy::class, 'viewAny');
        $params = $method->getParameters();

        // Assert
        $this->assertCount(1, $params);
        $this->assertSame('authUser', $params[0]->getName());
    }

    /**
     * Test record methods have AuthUser + Role parameters.
     *
     * view, update, delete, restore, forceDelete, replicate take
     * both AuthUser and Role parameters.
     */
    public function test_record_methods_have_auth_user_and_role(): void
    {
        // Arrange
        $recordMethods = ['view', 'update', 'delete', 'restore', 'forceDelete', 'replicate'];

        foreach ($recordMethods as $methodName) {
            $method = new ReflectionMethod(RolePolicy::class, $methodName);
            $params = $method->getParameters();

            // Assert: 2 parameters
            $this->assertCount(2, $params, "Method {$methodName} should have 2 parameters");
            $this->assertSame('authUser', $params[0]->getName());
            $this->assertSame('role', $params[1]->getName());
        }
    }

    /**
     * Test file has declare(strict_types=1).
     *
     * This was added during the PHPStan migration.
     */
    public function test_file_has_strict_types_declaration(): void
    {
        // Arrange
        $reflection = new ReflectionClass(RolePolicy::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);

        // Act: read the first few bytes to check for declare
        $content = file_get_contents($filename);
        $this->assertNotFalse($content);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }
}
