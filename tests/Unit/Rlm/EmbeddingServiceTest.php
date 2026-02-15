<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\Embeddings\NullDriver;
use Aicl\Rlm\EmbeddingService;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    // ========================================================================
    // resolveDriver() — returns NullDriver when configured as 'null'
    // ========================================================================

    public function test_resolve_driver_returns_null_driver_when_configured_as_null(): void
    {
        config(['aicl.rlm.embeddings.driver' => 'null']);

        $service = new EmbeddingService;

        $driver = $service->getDriver();

        $this->assertInstanceOf(NullDriver::class, $driver);
    }

    // ========================================================================
    // isAvailable() — false for NullDriver
    // ========================================================================

    public function test_is_available_returns_false_for_null_driver(): void
    {
        config(['aicl.rlm.embeddings.driver' => 'null']);

        $service = new EmbeddingService;

        $this->assertFalse($service->isAvailable());
    }

    // ========================================================================
    // getDimension() — returns 1536 for NullDriver
    // ========================================================================

    public function test_get_dimension_returns_1536_for_null_driver(): void
    {
        config(['aicl.rlm.embeddings.driver' => 'null']);

        $service = new EmbeddingService;

        $this->assertSame(1536, $service->getDimension());
    }

    // ========================================================================
    // generate() — returns null for NullDriver
    // ========================================================================

    public function test_generate_returns_null_for_null_driver(): void
    {
        config(['aicl.rlm.embeddings.driver' => 'null']);

        $service = new EmbeddingService;

        $result = $service->generate('test text');

        $this->assertNull($result);
    }

    // ========================================================================
    // generateBatch() — returns nulls for NullDriver
    // ========================================================================

    public function test_generate_batch_returns_nulls_for_null_driver(): void
    {
        config(['aicl.rlm.embeddings.driver' => 'null']);

        $service = new EmbeddingService;

        $results = $service->generateBatch(['hello', 'world']);

        $this->assertCount(2, $results);
        $this->assertNull($results[0]);
        $this->assertNull($results[1]);
    }

    public function test_generate_batch_returns_empty_array_for_empty_input(): void
    {
        config(['aicl.rlm.embeddings.driver' => 'null']);

        $service = new EmbeddingService;

        $results = $service->generateBatch([]);

        $this->assertSame([], $results);
    }

    // ========================================================================
    // getDriver() — caches the resolved driver
    // ========================================================================

    public function test_get_driver_returns_same_instance_on_repeated_calls(): void
    {
        config(['aicl.rlm.embeddings.driver' => 'null']);

        $service = new EmbeddingService;

        $driver1 = $service->getDriver();
        $driver2 = $service->getDriver();

        $this->assertSame($driver1, $driver2);
    }

    // ========================================================================
    // Fallback to NullDriver when no API keys configured
    // ========================================================================

    public function test_fallback_to_null_driver_when_no_provider_available(): void
    {
        // Explicitly clear all provider configurations
        config([
            'aicl.rlm.embeddings.driver' => null,
            'aicl.rlm.embeddings.openai.api_key' => null,
        ]);

        $service = new EmbeddingService;

        // Without any API keys or reachable services, should fall back to NullDriver
        $driver = $service->getDriver();

        $this->assertInstanceOf(NullDriver::class, $driver);
    }
}
