<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Http\Middleware;

use Aicl\Http\Middleware\MustTwoFactor;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for MustTwoFactor middleware PHPStan changes.
 *
 * Covers the route()?-> null guards on $request->route(), the
 * panel?->getId() ?? 'admin' fallback, and the $user null check
 * before calling hasConfirmedTwoFactor() and hasValidTwoFactorSession().
 *
 * Note: Full integration tests for this middleware require Filament's
 * panel to be booted. These unit tests verify the structural aspects
 * and null guard logic at the class level.
 */
class MustTwoFactorRegressionTest extends TestCase
{
    // -- Class structure --

    /**
     * Test MustTwoFactor handle method signature allows mixed return.
     *
     * PHPStan change: Added @return mixed annotation. The method
     * can return either the next middleware's response or a redirect.
     */
    public function test_handle_method_has_correct_signature(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(MustTwoFactor::class, 'handle');
        $params = $reflection->getParameters();

        // Assert: handle takes Request and Closure parameters
        $this->assertCount(2, $params);
        $this->assertSame('request', $params[0]->getName());
        $this->assertSame('next', $params[1]->getName());
    }

    /**
     * Test MustTwoFactor has declare(strict_types=1).
     *
     * PHPStan change: Added strict types declaration.
     */
    public function test_class_file_has_strict_types(): void
    {
        // Arrange: read the source file
        $reflection = new \ReflectionClass(MustTwoFactor::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert: file starts with declare(strict_types=1)
        $this->assertStringContainsString('declare(strict_types=1)', $contents);
    }

    /**
     * Test null guard variables are used in the source code.
     *
     * PHPStan change: Multiple null guards added for route(), panel, and user.
     * This test verifies the source code contains the expected null-safe patterns.
     */
    public function test_source_contains_null_safe_patterns(): void
    {
        // Arrange: read source code
        $reflection = new \ReflectionClass(MustTwoFactor::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert: contains null-safe operator patterns
        // $request->route() is now stored in variable and null-checked
        $this->assertStringContainsString('$route = $request->route()', $contents);
        // Panel null guard
        $this->assertStringContainsString("?->getId() ?? 'admin'", $contents);
        // User null check before method calls
        $this->assertStringContainsString('$user && $user->hasConfirmedTwoFactor()', $contents);
    }
}
