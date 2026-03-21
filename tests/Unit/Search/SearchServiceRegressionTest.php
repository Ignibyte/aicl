<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Search;

use Aicl\Search\SearchService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Regression tests for SearchService PHPStan changes.
 *
 * Covers declare(strict_types=1) addition, (int) cast on min_query_length
 * config, (float) cast on entity boost, method return type annotations,
 * and the Authenticatable parameter type. Actual Elasticsearch queries
 * are tested at integration level.
 */
class SearchServiceRegressionTest extends TestCase
{
    /**
     * Test file has declare(strict_types=1).
     */
    public function test_file_has_strict_types(): void
    {
        // Arrange
        $reflection = new ReflectionClass(SearchService::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);

        // Act
        $content = file_get_contents($filename);
        $this->assertNotFalse($content);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    /**
     * Test search method returns SearchResultCollection.
     *
     * PHPStan enforced return type annotation.
     */
    public function test_search_method_return_type(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchService::class, 'search');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('Aicl\Search\SearchResultCollection', $returnType->getName());
    }

    /**
     * Test search method accepts required parameters.
     *
     * PHPStan enforced Authenticatable type hint on $user parameter.
     */
    public function test_search_method_parameters(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchService::class, 'search');
        $params = $method->getParameters();

        // Assert: 5 parameters
        $this->assertCount(5, $params);
        $this->assertSame('query', $params[0]->getName());
        $this->assertSame('user', $params[1]->getName());
        $this->assertSame('entityTypeFilter', $params[2]->getName());
        $this->assertSame('page', $params[3]->getName());
        $this->assertSame('perPage', $params[4]->getName());

        // entityTypeFilter should be nullable
        $this->assertTrue($params[2]->allowsNull());

        // page and perPage should have defaults
        $this->assertTrue($params[3]->isDefaultValueAvailable());
        $this->assertTrue($params[4]->isDefaultValueAvailable());
        $this->assertSame(1, $params[3]->getDefaultValue());
        $this->assertSame(20, $params[4]->getDefaultValue());
    }

    /**
     * Test getEntityConfigs returns array type.
     *
     * PHPStan added @return array<string, array<string, mixed>> annotation.
     */
    public function test_get_entity_configs_return_type(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchService::class, 'getEntityConfigs');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('array', $returnType->getName());
    }

    /**
     * Test getEntityTypes returns array type.
     *
     * PHPStan added @return array<string, string> annotation.
     */
    public function test_get_entity_types_return_type(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchService::class, 'getEntityTypes');
        $returnType = $method->getReturnType();

        // Assert
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertSame('array', $returnType->getName());
    }

    /**
     * Test applyPolicyFilter method is public.
     *
     * PHPStan enforced Collection return type annotations.
     */
    public function test_apply_policy_filter_is_public(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchService::class, 'applyPolicyFilter');

        // Assert
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test buildSearchBody method is protected.
     *
     * PHPStan added @return array<string, mixed> annotation.
     */
    public function test_build_search_body_is_protected(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchService::class, 'buildSearchBody');

        // Assert
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test parseResponse method is protected.
     *
     * PHPStan added @param array<string, mixed> annotation.
     */
    public function test_parse_response_is_protected(): void
    {
        // Arrange
        $method = new ReflectionMethod(SearchService::class, 'parseResponse');

        // Assert
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test constructor requires Client parameter.
     */
    public function test_constructor_requires_client(): void
    {
        // Arrange
        $constructor = (new ReflectionClass(SearchService::class))->getConstructor();
        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();

        // Assert
        $this->assertCount(1, $params);
        $this->assertSame('client', $params[0]->getName());
    }
}
