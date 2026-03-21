<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Jobs;

use Aicl\Jobs\IndexSearchDocumentJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression tests for IndexSearchDocumentJob PHPStan changes.
 *
 * Covers declare(strict_types=1) addition, constructor readonly
 * properties, queue assignment, tries/backoff configuration,
 * and the ShouldQueue interface implementation. The handle() method
 * requires database and Elasticsearch so it's tested at integration level.
 */
class IndexSearchDocumentJobRegressionTest extends TestCase
{
    /**
     * Test job implements ShouldQueue.
     *
     * PHPStan enforced the interface implementation.
     */
    public function test_implements_should_queue(): void
    {
        // Arrange
        $reflection = new ReflectionClass(IndexSearchDocumentJob::class);

        // Assert
        $this->assertTrue($reflection->implementsInterface(ShouldQueue::class));
    }

    /**
     * Test constructor sets queue to 'search'.
     *
     * The queue property is set in the constructor: $this->queue = 'search'.
     */
    public function test_constructor_sets_search_queue(): void
    {
        // Arrange & Act
        $job = new IndexSearchDocumentJob(
            modelClass: 'App\\Models\\User',
            modelId: 'uuid-123',
        );

        // Assert
        $this->assertSame('search', $job->queue);
    }

    /**
     * Test constructor stores readonly properties.
     *
     * PHPStan enforced readonly modifiers on constructor properties.
     */
    public function test_constructor_stores_readonly_properties(): void
    {
        // Arrange & Act
        $job = new IndexSearchDocumentJob(
            modelClass: 'App\\Models\\Project',
            modelId: 'uuid-456',
            action: 'delete',
        );

        // Assert
        $this->assertSame('App\\Models\\Project', $job->modelClass);
        $this->assertSame('uuid-456', $job->modelId);
        $this->assertSame('delete', $job->action);
    }

    /**
     * Test default action is 'index'.
     *
     * The action parameter defaults to 'index' when not specified.
     */
    public function test_default_action_is_index(): void
    {
        // Arrange & Act
        $job = new IndexSearchDocumentJob(
            modelClass: 'App\\Models\\User',
            modelId: '1',
        );

        // Assert
        $this->assertSame('index', $job->action);
    }

    /**
     * Test tries is set to 3.
     */
    public function test_tries_is_three(): void
    {
        // Arrange
        $job = new IndexSearchDocumentJob('App\\Models\\User', '1');

        // Assert
        $this->assertSame(3, $job->tries);
    }

    /**
     * Test backoff is set to 5 seconds.
     */
    public function test_backoff_is_five(): void
    {
        // Arrange
        $job = new IndexSearchDocumentJob('App\\Models\\User', '1');

        // Assert
        $this->assertSame(5, $job->backoff);
    }

    /**
     * Test file has declare(strict_types=1).
     *
     * This was added during the PHPStan migration.
     */
    public function test_file_has_strict_types(): void
    {
        // Arrange
        $reflection = new ReflectionClass(IndexSearchDocumentJob::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);

        // Act
        $content = file_get_contents($filename);
        $this->assertNotFalse($content);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }
}
