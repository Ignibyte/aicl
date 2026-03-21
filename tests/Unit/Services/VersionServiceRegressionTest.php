<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Services;

use Aicl\Services\VersionService;
use Tests\TestCase;

/**
 * Regression tests for VersionService PHPStan changes.
 *
 * Tests the file_get_contents() === false null guard added to
 * parseVersionFrom(). Under strict_types, the previous code could
 * pass false to preg_match() when file_get_contents() failed.
 */
class VersionServiceRegressionTest extends TestCase
{
    /**
     * Test parseVersionFrom returns 'unknown' when file doesn't exist.
     *
     * PHPStan change: Added explicit check for file_get_contents() === false
     * which returns 'unknown' instead of passing false to preg_match().
     */
    public function test_project_version_returns_unknown_for_nonexistent_file(): void
    {
        // Arrange: temporarily rename changelog if it exists
        $path = base_path('CHANGELOG.md');
        $backup = $path.'.bak-regression';
        $existed = file_exists($path);

        if ($existed) {
            rename($path, $backup);
        }

        try {
            // Act
            $service = new VersionService;
            $version = $service->projectVersion();

            // Assert: should return 'unknown' without crashing
            $this->assertSame('unknown', $version);
        } finally {
            // Cleanup: restore file if it existed
            if ($existed) {
                rename($backup, $path);
            }
        }
    }

    /**
     * Test parseVersionFrom extracts version from valid changelog.
     *
     * Happy path: file exists and contains a version header.
     */
    public function test_project_version_parses_version_from_changelog(): void
    {
        // Arrange: create a temporary changelog with version header
        $tmpFile = tempnam(sys_get_temp_dir(), 'changelog_');
        file_put_contents($tmpFile, "# Changelog\n\n## [1.2.3] - 2026-03-20\n\n- Fix bug\n");

        try {
            // Act: invoke parseVersionFrom via reflection
            $service = new VersionService;
            $method = new \ReflectionMethod($service, 'parseVersionFrom');
            $method->setAccessible(true);
            $result = $method->invoke($service, $tmpFile);

            // Assert: should extract the version number
            $this->assertSame('1.2.3', $result);
        } finally {
            unlink($tmpFile);
        }
    }

    /**
     * Test parseVersionFrom returns 'unknown' when file has no version header.
     *
     * Edge case: file exists but contains no version pattern.
     */
    public function test_project_version_returns_unknown_when_no_version_header(): void
    {
        // Arrange: create a changelog without version header
        $tmpFile = tempnam(sys_get_temp_dir(), 'changelog_');
        file_put_contents($tmpFile, "# Changelog\n\nNo versions here.\n");

        try {
            // Act
            $service = new VersionService;
            $method = new \ReflectionMethod($service, 'parseVersionFrom');
            $method->setAccessible(true);
            $result = $method->invoke($service, $tmpFile);

            // Assert: should return 'unknown'
            $this->assertSame('unknown', $result);
        } finally {
            unlink($tmpFile);
        }
    }

    /**
     * Test parseVersionFrom returns 'unknown' for empty file.
     *
     * Edge case: file exists but is empty.
     */
    public function test_project_version_returns_unknown_for_empty_file(): void
    {
        // Arrange: create an empty file
        $tmpFile = tempnam(sys_get_temp_dir(), 'changelog_');
        file_put_contents($tmpFile, '');

        try {
            // Act
            $service = new VersionService;
            $method = new \ReflectionMethod($service, 'parseVersionFrom');
            $method->setAccessible(true);
            $result = $method->invoke($service, $tmpFile);

            // Assert: should return 'unknown'
            $this->assertSame('unknown', $result);
        } finally {
            unlink($tmpFile);
        }
    }
}
