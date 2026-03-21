<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Search;

use Aicl\Search\SearchIndexingService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Regression tests for SearchIndexingService PHPStan changes.
 *
 * Covers declare(strict_types=1) addition, method return type
 * declarations, parameter type annotations, and the (int) cast
 * in bulkIndex(). The actual ES client interactions are tested
 * at integration level with mocked clients.
 */
class SearchIndexingServiceRegressionTest extends TestCase
{
    /**
     * Test file has declare(strict_types=1).
     */
    public function test_file_has_strict_types(): void
    {
        // Arrange
        $reflection = new ReflectionClass(SearchIndexingService::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);

        // Act
        $content = file_get_contents($filename);
        $this->assertNotFalse($content);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    /**
     * Test index method has void return type.
     *
     * PHPStan enforced return type.
     */
    public function test_index_method_returns_void(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'index');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('void', $returnType->getName());
    }

    /**
     * Test delete method has void return type.
     */
    public function test_delete_method_returns_void(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'delete');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('void', $returnType->getName());
    }

    /**
     * Test createIndex method has void return type.
     */
    public function test_create_index_returns_void(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'createIndex');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('void', $returnType->getName());
    }

    /**
     * Test deleteIndex method has void return type.
     */
    public function test_delete_index_returns_void(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'deleteIndex');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('void', $returnType->getName());
    }

    /**
     * Test indexExists method returns bool.
     */
    public function test_index_exists_returns_bool(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'indexExists');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('bool', $returnType->getName());
    }

    /**
     * Test bulkIndex method returns int.
     *
     * PHPStan enforced (int) cast on the return: return (int) $indexed.
     */
    public function test_bulk_index_returns_int(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'bulkIndex');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('int', $returnType->getName());
    }

    /**
     * Test bulkIndex accepts nullable indexName parameter.
     *
     * PHPStan enforced ?string type hint on $indexName.
     */
    public function test_bulk_index_accepts_nullable_index_name(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'bulkIndex');
        $params = $method->getParameters();

        // Assert: 2 parameters — documents array and optional indexName
        $this->assertCount(2, $params);
        $this->assertSame('documents', $params[0]->getName());
        $this->assertSame('indexName', $params[1]->getName());
        $this->assertTrue($params[1]->allowsNull());
    }

    /**
     * Test getIndexAlias returns string.
     *
     * PHPStan enforced string return type.
     */
    public function test_get_index_alias_returns_string(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'getIndexAlias');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('string', $returnType->getName());
    }

    /**
     * Test swapAlias method has void return type.
     */
    public function test_swap_alias_returns_void(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'swapAlias');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('void', $returnType->getName());
    }

    /**
     * Test ensureIndex method has void return type.
     */
    public function test_ensure_index_returns_void(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchIndexingService::class, 'ensureIndex');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('void', $returnType->getName());
    }

    /**
     * Test constructor requires Client parameter.
     */
    public function test_constructor_requires_client(): void
    {
        // Arrange
        $constructor = (new ReflectionClass(SearchIndexingService::class))->getConstructor();
        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();

        // Assert: single Client parameter
        $this->assertCount(1, $params);
        $this->assertSame('client', $params[0]->getName());
    }
}
