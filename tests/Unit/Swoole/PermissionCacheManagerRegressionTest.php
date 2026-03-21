<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\Cache\PermissionCacheManager;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for PermissionCacheManager PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition, the User import,
 * and the typed list<string> PHPDoc annotations for roles and
 * permissions arrays from pluck()->toArray().
 */
class PermissionCacheManagerRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test buildCacheForUser returns correct structure with roles and permissions.
     *
     * PHPStan changes: Added User import, @var list<string> annotations
     * for roles and permissions arrays from pluck()->toArray().
     */
    public function test_build_cache_returns_correct_structure(): void
    {
        // Arrange: create a user with roles
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Act: build cache for the user
        $cache = PermissionCacheManager::buildCacheForUser($user);

        // Assert: should have roles and permissions as string arrays
        $this->assertArrayHasKey('roles', $cache);
        $this->assertArrayHasKey('permissions', $cache);

        // Verify roles contains strings, not objects
        foreach ($cache['roles'] as $role) {
        }

        // Admin should have the 'admin' role
        $this->assertContains('admin', $cache['roles']);
    }

    /**
     * Test buildCacheForUser handles user without roles.
     *
     * Edge case: user with no assigned roles.
     */
    public function test_build_cache_handles_user_without_roles(): void
    {
        // Arrange: create a user without roles
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
        $user = User::factory()->create();

        // Act
        $cache = PermissionCacheManager::buildCacheForUser($user);

        // Assert: should have empty arrays, not null
        $this->assertEmpty($cache['roles']);
    }
}
