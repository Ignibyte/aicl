<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\AI\Jobs;

use Aicl\AI\Jobs\AiConversationStreamJob;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression tests for AiConversationStreamJob PHPStan changes.
 *
 * Tests the is_object() null guard added to extractUsage() which
 * replaced a simple truthy check on the generator return value.
 * Same change pattern as AiStreamJob but in the conversation-specific job.
 */
class AiConversationStreamJobRegressionTest extends TestCase
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
        iterator_to_array($generator);

        // Act: call extractUsage via reflection
        $result = $this->callExtractUsage($generator);

        // Assert: should return empty array, not crash
        $this->assertSame([], $result);
    }

    /**
     * Test extractUsage handles non-object generator return values.
     *
     * PHPStan change: is_object() replaces truthy check. A numeric
     * return like 42 was previously truthy but would fail method_exists().
     */
    public function test_extract_usage_returns_empty_when_generator_returns_integer(): void
    {
        // Arrange: create a generator that returns an integer
        $generator = $this->createGeneratorReturning(42);
        iterator_to_array($generator);

        // Act
        $result = $this->callExtractUsage($generator);

        // Assert: non-object should be treated as no usage data
        $this->assertSame([], $result);
    }

    /**
     * Test extractUsage returns empty when object lacks getUsage method.
     *
     * Verifies method_exists() guard on the response object.
     */
    public function test_extract_usage_returns_empty_when_no_get_usage_method(): void
    {
        // Arrange: plain object without getUsage()
        $generator = $this->createGeneratorReturning(new \stdClass);
        iterator_to_array($generator);

        // Act
        $result = $this->callExtractUsage($generator);

        // Assert
        $this->assertSame([], $result);
    }

    /**
     * Test extractUsage extracts tokens from valid response.
     *
     * Happy path: response has getUsage() returning usage data.
     */
    public function test_extract_usage_returns_tokens_from_valid_response(): void
    {
        // Arrange: build a response with usage data
        $usage = new class
        {
            public int $inputTokens = 150;

            public int $outputTokens = 300;
        };

        $response = new class($usage)
        {
            private object $usage;

            public function __construct(object $u)
            {
                $this->usage = $u;
            }

            public function getUsage(): object
            {
                return $this->usage;
            }
        };

        $generator = $this->createGeneratorReturning($response);
        iterator_to_array($generator);

        // Act
        $result = $this->callExtractUsage($generator);

        // Assert: token counts should be extracted correctly
        $this->assertSame([
            'input_tokens' => 150,
            'output_tokens' => 300,
        ], $result);
    }

    /**
     * Helper: create a generator that yields a chunk and returns the given value.
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
     * Helper: invoke the private extractUsage method via reflection.
     *
     * Constructor: streamId, conversationId, userId (int).
     *
     * @return array<string, int>
     */
    private function callExtractUsage(\Generator $generator): array
    {
        // Create job instance with correct constructor arg types
        $job = new AiConversationStreamJob(
            streamId: 'test-stream',
            conversationId: 'test-conv-id',
            userId: 1,
        );

        $method = new ReflectionMethod(AiConversationStreamJob::class, 'extractUsage');
        $method->setAccessible(true);

        return $method->invoke($job, $generator);
    }
}
