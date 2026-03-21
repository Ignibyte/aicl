<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Commands;

use Tests\TestCase;

/**
 * Regression tests for PipelineContextCommand PHPStan changes.
 *
 * Tests the file_get_contents() === false null guard and the
 * glob() ?: [] null coalescing in findPipelineDocument().
 * Under strict_types, these changes prevent type errors when
 * file operations return false.
 */
class PipelineContextCommandRegressionTest extends TestCase
{
    /**
     * Test command fails gracefully when pipeline document doesn't exist.
     *
     * PHPStan change: Added file_get_contents() === false check
     * that returns FAILURE instead of passing false to string functions.
     */
    public function test_command_fails_for_nonexistent_pipeline(): void
    {
        // Act: run with a non-existent entity name
        $this->artisan('aicl:pipeline-context', [
            'entity' => 'NonExistentEntity',
        ])
            // Assert: should fail gracefully, not crash
            /** @phpstan-ignore-next-line */
            ->assertFailed();
    }

    /**
     * Test command handles glob returning false/empty.
     *
     * PHPStan change: glob($pattern) ?: [] ensures false return
     * from glob() doesn't break foreach iteration.
     */
    public function test_command_handles_empty_pipeline_directory(): void
    {
        // Act: run with entity that has no pipeline document
        $this->artisan('aicl:pipeline-context', [
            'entity' => 'DefinitelyNoMatchHere12345',
        ])
            // Assert: should fail gracefully
            /** @phpstan-ignore-next-line */
            ->assertFailed();
    }
}
