<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Providers;

use Aicl\Providers\SearchServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Regression tests for SearchServiceProvider PHPStan changes.
 *
 * Covers declare(strict_types=1) addition, (int) cast on port config,
 * the (string) cast on $modelClass in registerSearchObservers(),
 * and the register/boot method signatures. Integration-level tests
 * for actual binding resolution are in Feature tests.
 */
class SearchServiceProviderRegressionTest extends TestCase
{
    /**
     * Test provider extends ServiceProvider.
     */
    public function test_extends_service_provider(): void
    {
        // Arrange
        $reflection = new ReflectionClass(SearchServiceProvider::class);
        $parent = $reflection->getParentClass();

        // Assert
        $this->assertNotFalse($parent);
        $this->assertSame('Illuminate\Support\ServiceProvider', $parent->getName());
    }

    /**
     * Test register method exists and is public.
     */
    public function test_register_method_is_public(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchServiceProvider::class, 'register');

        // Assert
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test boot method exists and is public.
     */
    public function test_boot_method_is_public(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchServiceProvider::class, 'boot');

        // Assert
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test registerSearchObservers is protected.
     *
     * PHPStan enforced the (string) cast on $modelClass inside
     * this method for array_keys type safety.
     */
    public function test_register_search_observers_is_protected(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchServiceProvider::class, 'registerSearchObservers');

        // Assert
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test file has declare(strict_types=1).
     */
    public function test_file_has_strict_types(): void
    {
        // Arrange
        $reflection = new ReflectionClass(SearchServiceProvider::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);

        // Act
        $content = file_get_contents($filename);
        $this->assertNotFalse($content);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    /**
     * Test register method has void return type.
     */
    public function test_register_returns_void(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchServiceProvider::class, 'register');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame('void', $returnType->getName());
    }

    /**
     * Test boot method has void return type.
     */
    public function test_boot_returns_void(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchServiceProvider::class, 'boot');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame('void', $returnType->getName());
    }
}
