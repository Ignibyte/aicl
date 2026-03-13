<?php

namespace Aicl\Tests\Unit\Search;

use Aicl\Search\PermissionFilterBuilder;
use App\Models\User;
use Tests\TestCase;

class PermissionFilterBuilderTest extends TestCase
{
    protected PermissionFilterBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PermissionFilterBuilder;
    }

    public function test_authenticated_visibility_allows_all_users(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $configs = [
            'App\\Models\\Task' => [
                'visibility' => 'authenticated',
            ],
        ];

        $filters = $this->builder->buildFilters($user, $configs);

        $this->assertNotEmpty($filters);
        // Should contain a should clause allowing the entity type
        $shouldClauses = $filters[0]['bool']['should'] ?? [];
        $this->assertNotEmpty($shouldClauses);
    }

    public function test_role_visibility_excludes_unauthorized_users(): void
    {
        $this->artisan('db:seed', ['--class' => 'Aicl\\Database\\Seeders\\RoleSeeder']);

        $user = User::factory()->create();
        $user->assignRole('viewer');

        $configs = [
            'App\\Models\\User' => [
                'visibility' => 'role:admin',
            ],
        ];

        $filters = $this->builder->buildFilters($user, $configs);

        // Viewer doesn't have admin role — should get empty filters (excluded)
        $shouldClauses = $filters[0]['bool']['should'] ?? [];
        $this->assertEmpty($shouldClauses);
    }

    public function test_role_visibility_allows_super_admin(): void
    {
        $this->artisan('db:seed', ['--class' => 'Aicl\\Database\\Seeders\\RoleSeeder']);

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $configs = [
            'App\\Models\\User' => [
                'visibility' => 'role:admin',
            ],
        ];

        $filters = $this->builder->buildFilters($user, $configs);

        $shouldClauses = $filters[0]['bool']['should'] ?? [];
        $this->assertNotEmpty($shouldClauses);
    }

    public function test_owner_visibility_filters_by_owner_id(): void
    {
        $user = User::factory()->make(['id' => 42]);

        $configs = [
            'App\\Models\\Task' => [
                'visibility' => 'owner',
            ],
        ];

        $filters = $this->builder->buildFilters($user, $configs);

        $shouldClauses = $filters[0]['bool']['should'] ?? [];
        $this->assertNotEmpty($shouldClauses);

        // Should contain owner_id filter
        $json = json_encode($shouldClauses);
        $this->assertStringContainsString('owner_id', $json);
        $this->assertStringContainsString('42', $json);
    }

    public function test_owner_plus_admin_allows_admin_to_see_all(): void
    {
        $this->artisan('db:seed', ['--class' => 'Aicl\\Database\\Seeders\\RoleSeeder']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $configs = [
            'App\\Models\\Task' => [
                'visibility' => 'owner+admin',
            ],
        ];

        $filters = $this->builder->buildFilters($user, $configs);

        $shouldClauses = $filters[0]['bool']['should'] ?? [];
        $this->assertNotEmpty($shouldClauses);

        // Admin should get a simple term filter (no owner_id restriction)
        $json = json_encode($shouldClauses);
        $this->assertStringNotContainsString('owner_id', $json);
    }

    public function test_policy_visibility_returns_all_results(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $configs = [
            'App\\Models\\Task' => [
                'visibility' => 'policy',
            ],
        ];

        $filters = $this->builder->buildFilters($user, $configs);

        $shouldClauses = $filters[0]['bool']['should'] ?? [];
        $this->assertNotEmpty($shouldClauses);

        // Policy visibility should not have owner_id filter
        $json = json_encode($shouldClauses);
        $this->assertStringNotContainsString('owner_id', $json);
    }

    public function test_empty_configs_returns_empty_filters(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $filters = $this->builder->buildFilters($user, []);

        $this->assertEmpty($filters);
    }
}
