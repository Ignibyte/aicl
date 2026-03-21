<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\AI\Jobs;

use Aicl\AI\Jobs\AiStreamJob;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression tests for AiStreamJob PHPStan changes.
 *
 * Tests the is_object() null guard added to extractUsage() which
 * replaced a simple truthy check on the generator return value.
 * Under strict_types, the previous check could pass for non-object
 * truthy values (e.g., arrays, strings) causing method_exists()
 * to receive an invalid argument.
 */
class AiStreamJobRegressionTest extends TestCase
{
    /**
     * Test extractUsage returns empty array when generator returns null.
     *
     * PHPStan change: is_object($response) check ensures null return
     * from a generator doesn't get passed to method_exists().
     */
    public function test_extract_usage_returns_empty_when_generator_returns_null(): void
    {
        // Arrange: create a generator that returns null
        $generator = $this->createGeneratorReturning(null);

        // Exhaust the generator so getReturn() works
        iterator_to_array($generator);

        // Act: call extractUsage via reflection (userId is int)
        $result = $this->callExtractUsage(new AiStreamJob(
            streamId: 'test-stream',
            userId: 1,
            prompt: 'test',
        ), $generator);

        // Assert: should return empty array, not crash
        $this->assertSame([], $result);
    }

    /**
     * Test extractUsage returns empty array when generator returns non-object.
     *
     * PHPStan change: is_object() check replaces truthy check.
     * A string or integer return value was previously truthy but
     * would fail method_exists() under strict types.
     */
    public function test_extract_usage_returns_empty_when_generator_returns_string(): void
    {
        // Arrange: create a generator that returns a string (non-object)
        $generator = $this->createGeneratorReturning('not-an-object');

        // Exhaust the generator
        iterator_to_array($generator);

        // Act
        $result = $this->callExtractUsage(new AiStreamJob(
            streamId: 'test-stream',
            userId: 1,
            prompt: 'test',
        ), $generator);

        // Assert: non-object should be treated as no usage data
        $this->assertSame([], $result);
    }

    /**
     * Test extractUsage returns empty array when object has no getUsage method.
     *
     * Verifies the method_exists() check works correctly with a
     * real object that doesn't implement getUsage().
     */
    public function test_extract_usage_returns_empty_when_object_lacks_get_usage(): void
    {
        // Arrange: create a generator that returns a plain object without getUsage
        $obj = new \stdClass;
        $generator = $this->createGeneratorReturning($obj);

        // Exhaust the generator
        iterator_to_array($generator);

        // Act
        $result = $this->callExtractUsage(new AiStreamJob(
            streamId: 'test-stream',
            userId: 1,
            prompt: 'test',
        ), $generator);

        // Assert: object without getUsage should return empty
        $this->assertSame([], $result);
    }

    /**
     * Test extractUsage returns usage when object has getUsage returning data.
     *
     * Happy path: verifies that a properly formed response object
     * with getUsage() returns the expected token counts.
     */
    public function test_extract_usage_returns_tokens_when_response_has_usage(): void
    {
        // Arrange: create a response object with getUsage method
        $usage = new class
        {
            public int $inputTokens = 42;

            public int $outputTokens = 100;
        };

        $response = new class($usage)
        {
            private object $usage;

            public function __construct(object $usage)
            {
                $this->usage = $usage;
            }

            public function getUsage(): object
            {
                return $this->usage;
            }
        };

        $generator = $this->createGeneratorReturning($response);

        // Exhaust the generator
        iterator_to_array($generator);

        // Act
        $result = $this->callExtractUsage(new AiStreamJob(
            streamId: 'test-stream',
            userId: 1,
            prompt: 'test',
        ), $generator);

        // Assert: should extract input and output tokens
        $this->assertSame([
            'input_tokens' => 42,
            'output_tokens' => 100,
        ], $result);
    }

    /**
     * Test extractUsage returns empty when getUsage returns null.
     *
     * Edge case: object has getUsage but it returns null.
     */
    public function test_extract_usage_returns_empty_when_get_usage_returns_null(): void
    {
        // Arrange: create a response with getUsage returning null
        $response = new class
        {
            /** @phpstan-ignore-next-line */
            public function getUsage(): ?object
            {
                return null;
            }
        };

        $generator = $this->createGeneratorReturning($response);
        iterator_to_array($generator);

        // Act
        $result = $this->callExtractUsage(new AiStreamJob(
            streamId: 'test-stream',
            userId: 1,
            prompt: 'test',
        ), $generator);

        // Assert: null usage should return empty array
        $this->assertSame([], $result);
    }

    /**
     * Helper: create a generator that returns the given value.
     *
     * @return \Generator<int, string, mixed, mixed>
     */
    private function createGeneratorReturning(mixed $returnValue): \Generator
    {
        return (function () use ($returnValue): \Generator {
            yield 'chunk';

            return $returnValue;
        })();
    }

    /**
     * Helper: call the private extractUsage method via reflection.
     *
     * @return array<string, int>
     */
    private function callExtractUsage(AiStreamJob $job, \Generator $generator): array
    {
        $method = new ReflectionMethod(AiStreamJob::class, 'extractUsage');
        $method->setAccessible(true);

        return $method->invoke($job, $generator);
    }
}
