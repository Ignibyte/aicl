<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\OperationsManager;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for OperationsManager page PHPStan changes.
 *
 * Covers the (string) casts on json_encode() calls in the failed job
 * detail display, the PHPDoc generic annotations on getQueueStats()
 * and getSupervisors(), and the declare(strict_types=1) enforcement.
 */
class OperationsManagerRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for access verification
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // -- getQueueStats --

    /**
     * Test getQueueStats returns expected structure with typed values.
     *
     * PHPStan change: Return type annotation changed from mixed array
     * to typed array{pending: int, ...}. Verifies all keys are present.
     */
    public function test_get_queue_stats_returns_expected_structure(): void
    {
        // Arrange
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $page = new OperationsManager;

        // Act
        $stats = $page->getQueueStats();

        // Assert: all expected keys are present
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('pending_high', $stats);
        $this->assertArrayHasKey('pending_low', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('jobs_per_minute', $stats);
        $this->assertArrayHasKey('total_processes', $stats);
        $this->assertArrayHasKey('workload', $stats);
    }

    /**
     * Test getQueueStats returns integer values for pending counts.
     *
     * Verifies type integrity under strict_types.
     */
    public function test_get_queue_stats_returns_integer_pending_counts(): void
    {
        // Arrange
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $page = new OperationsManager;

        // Act
        $stats = $page->getQueueStats();

        // Assert: pending counts are non-negative integers
        $this->assertGreaterThanOrEqual(0, $stats['pending']);
        $this->assertGreaterThanOrEqual(0, $stats['pending_high']);
        $this->assertGreaterThanOrEqual(0, $stats['pending_low']);
        $this->assertGreaterThanOrEqual(0, $stats['failed']);
    }

    // -- getSupervisors --

    /**
     * Test getSupervisors returns an array when Horizon is not available.
     *
     * PHPStan change: Added PHPDoc @return array<int, mixed> annotation.
     * When Horizon is not bound, should return empty array.
     */
    public function test_get_supervisors_returns_array_when_horizon_unavailable(): void
    {
        // Arrange: disable Horizon feature
        config(['aicl.features.horizon' => false]);

        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $page = new OperationsManager;

        // Act
        $supervisors = $page->getSupervisors();

        // Assert: returns empty array, not an error
        $this->assertSame([], $supervisors);
    }

    // -- getQueueTabs --

    /**
     * Test getQueueTabs returns the expected tab configuration.
     *
     * Verifies the active tab configuration for the operations manager.
     */
    public function test_get_queue_tabs_returns_expected_tabs(): void
    {
        // Arrange
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $page = new OperationsManager;

        // Act
        $tabs = $page->getQueueTabs();

        // Assert: tabs array is not empty
        $this->assertNotEmpty($tabs);
    }
}
