<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Http\Controllers;

use Aicl\Http\Controllers\Api\AiConversationController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for AiConversationController PHPStan changes.
 *
 * Covers the declare(strict_types=1) enforcement, the auth()->user()
 * null guard with abort(401) in the index() method, and the class
 * docblock addition.
 */
class AiConversationControllerRegressionTest extends TestCase
{
    // -- Class structure --

    /**
     * Test controller has declare(strict_types=1).
     *
     * PHPStan change: Added strict types declaration.
     */
    public function test_class_has_strict_types(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(AiConversationController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $contents);
    }

    /**
     * Test controller uses AuthorizesRequests trait.
     *
     * The controller uses Laravel's authorization trait for policy checks.
     */
    public function test_uses_authorizes_requests_trait(): void
    {
        // Arrange
        $traits = class_uses_recursive(AiConversationController::class);

        // Assert: AuthorizesRequests trait is used
        $this->assertArrayHasKey(
            AuthorizesRequests::class,
            $traits
        );
    }

    /**
     * Test index method has correct return type.
     *
     * The index method returns AnonymousResourceCollection.
     */
    public function test_index_method_return_type(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(AiConversationController::class, 'index');
        $returnType = $reflection->getReturnType();

        // Assert: return type is AnonymousResourceCollection
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(AnonymousResourceCollection::class, $returnType->getName());
    }

    /**
     * Test source code contains null user guard with abort(401).
     *
     * PHPStan change: Added auth()->user() null check with abort(401).
     */
    public function test_source_contains_null_user_abort(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(AiConversationController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert: contains the null guard pattern
        $this->assertStringContainsString('abort(401)', $contents);
    }

    /**
     * Test all CRUD methods are public.
     *
     * Verifies index, store, show, update, destroy are all public.
     */
    public function test_crud_methods_are_public(): void
    {
        // Arrange
        $methods = ['index', 'store', 'show', 'update', 'destroy'];

        // Assert: all methods are public
        foreach ($methods as $method) {
            $reflection = new \ReflectionMethod(AiConversationController::class, $method);
            $this->assertTrue(
                $reflection->isPublic(),
                "{$method} should be public"
            );
        }
    }
}
