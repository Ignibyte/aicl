<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Jobs;

use Aicl\Jobs\ReindexPermissionsJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression tests for ReindexPermissionsJob PHPStan changes.
 *
 * Covers declare(strict_types=1) addition, constructor readonly
 * property with union type (int|string), queue assignment, and
 * tries/backoff configuration. The handle() method requires database
 * and Elasticsearch so it's tested at integration level.
 */
class ReindexPermissionsJobRegressionTest extends TestCase
{
    /**
     * Test job implements ShouldQueue.
     */
    public function test_implements_should_queue(): void
    {
        // Arrange
        $reflection = new ReflectionClass(ReindexPermissionsJob::class);

        // Assert
        $this->assertTrue($reflection->implementsInterface(ShouldQueue::class));
    }

    /**
     * Test constructor sets queue to 'search'.
     */
    public function test_constructor_sets_search_queue(): void
    {
        // Arrange & Act
        $job = new ReindexPermissionsJob(userId: 1);

        // Assert
        $this->assertSame('search', $job->queue);
    }

    /**
     * Test constructor accepts integer userId.
     *
     * PHPStan enforced int|string union type on constructor.
     */
    public function test_constructor_accepts_integer_user_id(): void
    {
        // Arrange & Act
        $job = new ReindexPermissionsJob(userId: 42);

        // Assert
        $this->assertSame(42, $job->userId);
    }

    /**
     * Test constructor accepts string userId.
     *
     * UUID-based user IDs are strings. PHPStan enforced the
     * int|string union type.
     */
    public function test_constructor_accepts_string_user_id(): void
    {
        // Arrange & Act
        $job = new ReindexPermissionsJob(userId: 'uuid-abc-123');

        // Assert
        $this->assertSame('uuid-abc-123', $job->userId);
    }

    /**
     * Test tries is set to 3.
     */
    public function test_tries_is_three(): void
    {
        // Arrange
        $job = new ReindexPermissionsJob(1);

        // Assert
        $this->assertSame(3, $job->tries);
    }

    /**
     * Test backoff is set to 10 seconds.
     */
    public function test_backoff_is_ten(): void
    {
        // Arrange
        $job = new ReindexPermissionsJob(1);

        // Assert
        $this->assertSame(10, $job->backoff);
    }

    /**
     * Test userId is readonly.
     *
     * PHPStan migration added readonly modifier.
     */
    public function test_user_id_is_readonly(): void
    {
        // Arrange
        $reflection = new ReflectionClass(ReindexPermissionsJob::class);
        $property = $reflection->getProperty('userId');

        // Assert
        $this->assertTrue($property->isReadOnly());
    }

    /**
     * Test file has declare(strict_types=1).
     */
    public function test_file_has_strict_types(): void
    {
        // Arrange
        $reflection = new ReflectionClass(ReindexPermissionsJob::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);

        // Act
        $content = file_get_contents($filename);
        $this->assertNotFalse($content);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }
}
