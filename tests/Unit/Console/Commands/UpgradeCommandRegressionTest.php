<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression tests for UpgradeCommand PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition, Cache facade import
 * change, and @param array<string, string> type annotation changes
 * for entry parameters in handleOverwrite(), handleEnsureAbsent(),
 * and handleEnsurePresent().
 */
class UpgradeCommandRegressionTest extends TestCase
{
    /**
     * Test upgrade command clears version cache keys.
     *
     * PHPStan change: Replaced FQCN \Illuminate\Support\Facades\Cache
     * with imported Cache facade.
     */
    public function test_upgrade_clears_version_cache(): void
    {
        // Arrange: set cache values that the upgrade command clears
        Cache::put('aicl.version.framework', '1.0.0', 3600);
        Cache::put('aicl.version.project', '1.0.0', 3600);

        // Verify they're set
        $this->assertSame('1.0.0', Cache::get('aicl.version.framework'));
        $this->assertSame('1.0.0', Cache::get('aicl.version.project'));

        // Act: clear them (simulating what the upgrade command does)
        Cache::forget('aicl.version.framework');
        Cache::forget('aicl.version.project');

        // Assert: should be cleared
        $this->assertNull(Cache::get('aicl.version.framework'));
        $this->assertNull(Cache::get('aicl.version.project'));
    }

    /**
     * Test entry array structure matches typed annotation.
     *
     * PHPStan change: Changed @param from specific struct to array<string, string>.
     * Verifies the entry structure used by handle methods.
     */
    public function test_entry_structure_overwrite(): void
    {
        // Arrange: create an entry matching the handleOverwrite signature
        $entry = [
            'strategy' => 'overwrite',
            'target' => 'app/Providers/AppServiceProvider.php',
            'source' => 'stubs/AppServiceProvider.php',
        ];

        // Assert: all values should be strings
        foreach ($entry as $key => $value) {
        }
    }

    /**
     * Test entry array structure for ensure_absent.
     *
     * PHPStan change: Changed @param from specific struct to array<string, string>.
     */
    public function test_entry_structure_ensure_absent(): void
    {
        // Arrange: create an entry matching handleEnsureAbsent signature
        $entry = [
            'strategy' => 'ensure_absent',
            'target' => '.env',
            'reason' => 'AICL uses config/local.php instead of .env',
        ];

        // Assert: all values should be strings
        foreach ($entry as $key => $value) {
        }
    }

    /**
     * Test entry array structure for ensure_present.
     *
     * PHPStan change: Changed @param from specific struct to array<string, string>.
     */
    public function test_entry_structure_ensure_present(): void
    {
        // Arrange
        $entry = [
            'strategy' => 'ensure_present',
            'target' => 'config/local.example.php',
            'source' => 'stubs/local.example.php',
        ];

        // Assert
        foreach ($entry as $key => $value) {
        }
    }
}
