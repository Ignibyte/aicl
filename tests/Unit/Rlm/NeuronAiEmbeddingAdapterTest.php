<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\Embeddings\NeuronAiEmbeddingAdapter;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use PHPUnit\Framework\TestCase;

class NeuronAiEmbeddingAdapterTest extends TestCase
{
    // ========================================================================
    // embed() — delegates to NeuronAI provider
    // ========================================================================

    public function test_embed_delegates_to_neuron_ai_provider(): void
    {
        $expected = [0.1, 0.2, 0.3, 0.4, 0.5];

        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->expects($this->once())
            ->method('embedText')
            ->with('hello world')
            ->willReturn($expected);

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 5, padToTarget: false);

        $result = $adapter->embed('hello world');

        $this->assertSame($expected, $result);
    }

    public function test_embed_returns_raw_vector_when_pad_to_target_is_false(): void
    {
        $vector = [0.1, 0.2, 0.3];

        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->method('embedText')->willReturn($vector);

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 5, padToTarget: false);

        $result = $adapter->embed('test');

        // Without padding, the raw vector is returned regardless of dimension
        $this->assertSame($vector, $result);
        $this->assertCount(3, $result);
    }

    // ========================================================================
    // embed() — zero-padding
    // ========================================================================

    public function test_embed_zero_pads_shorter_vector_when_pad_to_target_is_true(): void
    {
        $shortVector = [0.1, 0.2, 0.3];

        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->method('embedText')->willReturn($shortVector);

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 6, padToTarget: true);

        $result = $adapter->embed('test');

        $this->assertCount(6, $result);
        $this->assertSame(0.1, $result[0]);
        $this->assertSame(0.2, $result[1]);
        $this->assertSame(0.3, $result[2]);
        $this->assertSame(0.0, $result[3]);
        $this->assertSame(0.0, $result[4]);
        $this->assertSame(0.0, $result[5]);
    }

    public function test_embed_does_not_pad_when_vector_matches_target_dimension(): void
    {
        $vector = [0.1, 0.2, 0.3, 0.4, 0.5];

        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->method('embedText')->willReturn($vector);

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 5, padToTarget: true);

        $result = $adapter->embed('test');

        $this->assertCount(5, $result);
        $this->assertSame($vector, $result);
    }

    // ========================================================================
    // embed() — truncation
    // ========================================================================

    public function test_embed_truncates_vector_exceeding_target_dimension(): void
    {
        $longVector = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8];

        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->method('embedText')->willReturn($longVector);

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 5, padToTarget: true);

        $result = $adapter->embed('test');

        $this->assertCount(5, $result);
        $this->assertSame([0.1, 0.2, 0.3, 0.4, 0.5], $result);
    }

    public function test_embed_truncates_when_vector_exceeds_and_pad_is_true(): void
    {
        $longVector = array_fill(0, 2000, 0.5);

        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->method('embedText')->willReturn($longVector);

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 1536, padToTarget: true);

        $result = $adapter->embed('test');

        $this->assertCount(1536, $result);
    }

    // ========================================================================
    // embedBatch()
    // ========================================================================

    public function test_embed_batch_maps_all_texts(): void
    {
        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->expects($this->exactly(3))
            ->method('embedText')
            ->willReturnOnConsecutiveCalls(
                [0.1, 0.2],
                [0.3, 0.4],
                [0.5, 0.6],
            );

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 2, padToTarget: false);

        $results = $adapter->embedBatch(['hello', 'world', 'test']);

        $this->assertCount(3, $results);
        $this->assertSame([0.1, 0.2], $results[0]);
        $this->assertSame([0.3, 0.4], $results[1]);
        $this->assertSame([0.5, 0.6], $results[2]);
    }

    public function test_embed_batch_returns_empty_array_for_empty_input(): void
    {
        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->expects($this->never())->method('embedText');

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 1536, padToTarget: false);

        $results = $adapter->embedBatch([]);

        $this->assertSame([], $results);
    }

    public function test_embed_batch_applies_padding_to_each_text(): void
    {
        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->method('embedText')
            ->willReturnOnConsecutiveCalls(
                [0.1],
                [0.2, 0.3],
            );

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 3, padToTarget: true);

        $results = $adapter->embedBatch(['a', 'b']);

        $this->assertCount(2, $results);
        $this->assertCount(3, $results[0]);
        $this->assertCount(3, $results[1]);
        $this->assertSame([0.1, 0.0, 0.0], $results[0]);
        $this->assertSame([0.2, 0.3, 0.0], $results[1]);
    }

    // ========================================================================
    // dimension()
    // ========================================================================

    public function test_dimension_returns_target_dimension(): void
    {
        $provider = $this->createMock(EmbeddingsProviderInterface::class);

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 1536);

        $this->assertSame(1536, $adapter->dimension());
    }

    public function test_dimension_returns_custom_target(): void
    {
        $provider = $this->createMock(EmbeddingsProviderInterface::class);

        $adapter = new NeuronAiEmbeddingAdapter($provider, targetDimension: 768);

        $this->assertSame(768, $adapter->dimension());
    }

    // ========================================================================
    // Contract compliance
    // ========================================================================

    public function test_implements_embedding_driver_contract(): void
    {
        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $adapter = new NeuronAiEmbeddingAdapter($provider);

        $this->assertInstanceOf(\Aicl\Contracts\EmbeddingDriver::class, $adapter);
    }
}
