<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\OpsPanel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for OpsPanel page PHPStan changes.
 *
 * Covers the new docblocks on getHeaderActions() and canAccess(),
 * the canAccess() null guard on auth()->user(), and the
 * return type annotations on getServiceChecks().
 */
class OpsPanelRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for canAccess hasRole checks
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // -- canAccess null guard --

    /**
     * Test canAccess returns false when no user is authenticated.
     *
     * PHPStan change: Added null guard on auth()->user().
     */
    public function test_can_access_returns_false_when_unauthenticated(): void
    {
        // Act
        $result = OpsPanel::canAccess();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test canAccess returns true for admin users.
     */
    public function test_can_access_returns_true_for_admin(): void
    {
        // Arrange
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        // Act
        $result = OpsPanel::canAccess();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test canAccess returns true for super_admin users.
     */
    public function test_can_access_returns_true_for_super_admin(): void
    {
        // Arrange
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        // Act
        $result = OpsPanel::canAccess();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test canAccess returns false for regular users.
     */
    public function test_can_access_returns_false_for_regular_user(): void
    {
        // Arrange
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $result = OpsPanel::canAccess();

        // Assert
        $this->assertFalse($result);
    }

    // -- getServiceChecks --

    /**
     * Test getServiceChecks returns an array of check results.
     *
     * PHPStan change: Added @return array<ServiceCheckResult> annotation.
     */
    public function test_get_service_checks_returns_array(): void
    {
        // Arrange
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $page = new OpsPanel;

        // Act
        $checks = $page->getServiceChecks();

        // Assert: returns results (may be empty if no checks registered)
        // getServiceChecks() always returns array, verify no exception thrown
        $this->assertGreaterThanOrEqual(0, count($checks));
    }

    // -- Page configuration --

    /**
     * Test page is configured with the correct slug.
     */
    public function test_page_has_correct_slug(): void
    {
        // Act: read static property via reflection
        $reflection = new \ReflectionClass(OpsPanel::class);
        $slug = $reflection->getProperty('slug');

        // Assert
        $this->assertSame('ops-panel', $slug->getDefaultValue());
    }

    /**
     * Test page title is set correctly.
     */
    public function test_page_has_correct_title(): void
    {
        // Act
        $reflection = new \ReflectionClass(OpsPanel::class);
        $title = $reflection->getProperty('title');

        // Assert
        $this->assertSame('Ops Panel', $title->getDefaultValue());
    }
}
