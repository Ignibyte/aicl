<?php

declare(strict_types=1);

namespace Aicl\Search;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * @codeCoverageIgnore Elasticsearch dependency
 */
class PermissionFilterBuilder
{
    /**
     * Build ES bool.filter clauses for permission-based result filtering.
     *
     * @param array<string, array<string, mixed>> $entityConfigs All registered entity configs
     *
     * @return array<int, array<string, mixed>> ES filter clauses
     */
    public function buildFilters(Authenticatable $user, array $entityConfigs): array
    {
        $shouldClauses = [];

        foreach ($entityConfigs as $entityClass => $config) {
            $visibility = $config['visibility'] ?? 'authenticated';
            $entityFilter = $this->buildEntityFilter($user, $entityClass, $visibility);

            if ($entityFilter !== null) {
                $shouldClauses[] = $entityFilter;
            }
        }

        if (empty($shouldClauses)) {
            return [];
        }

        return [
            [
                'bool' => [
                    'should' => $shouldClauses,
                    'minimum_should_match' => 1,
                ],
            ],
        ];
    }

    /**
     * Build filter for a single entity type based on visibility rule.
     *
     * @return array<string, mixed>|null
     */
    protected function buildEntityFilter(Authenticatable $user, string $entityClass, string $visibility): ?array
    {
        // Parse visibility rule
        if ($visibility === 'authenticated') {
            // Any logged-in user can see — just match entity type
            return [
                'term' => ['entity_type' => $entityClass],
            ];
        }

        if ($visibility === 'policy') {
            // No ES filter — all results returned, policy check handles it
            return [
                'term' => ['entity_type' => $entityClass],
            ];
        }

        if (str_starts_with($visibility, 'role:')) {
            $requiredRole = substr($visibility, 5);

            return $this->buildRoleFilter($user, $entityClass, $requiredRole);
        }

        if ($visibility === 'owner') {
            return $this->buildOwnerFilter($user, $entityClass);
        }

        if ($visibility === 'owner+admin') {
            return $this->buildOwnerPlusAdminFilter($user, $entityClass);
        }

        // Unknown visibility — default to authenticated
        return [
            'term' => ['entity_type' => $entityClass],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildRoleFilter(Authenticatable $user, string $entityClass, string $requiredRole): ?array
    {
        if (! method_exists($user, 'hasRole')) { // @phpstan-ignore function.alreadyNarrowedType
            return null;
        }

        // super_admin sees everything
        if ($this->userHasRole($user, 'super_admin') || $this->userHasRole($user, $requiredRole)) {
            return [
                'term' => ['entity_type' => $entityClass],
            ];
        }

        // User doesn't have the required role — exclude this entity type
        return null;
    }

    /**
     * Check if a user has a given role (safe for any Authenticatable).
     */
    protected function userHasRole(Authenticatable $user, string $role): bool
    {
        if (! method_exists($user, 'hasRole')) { // @phpstan-ignore function.alreadyNarrowedType
            return false;
        }

        return $user->hasRole($role);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildOwnerFilter(Authenticatable $user, string $entityClass): array
    {
        $userId = (string) $user->getAuthIdentifier();

        // super_admin sees all
        if ($this->userHasRole($user, 'super_admin')) {
            return [
                'term' => ['entity_type' => $entityClass],
            ];
        }

        return [
            'bool' => [
                'must' => [
                    ['term' => ['entity_type' => $entityClass]],
                    ['term' => ['owner_id' => $userId]],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildOwnerPlusAdminFilter(Authenticatable $user, string $entityClass): array
    {
        $userId = (string) $user->getAuthIdentifier();

        // Admin or super_admin sees all of this type
        if ($this->userHasRole($user, 'super_admin') || $this->userHasRole($user, 'admin')) {
            return [
                'term' => ['entity_type' => $entityClass],
            ];
        }

        // Regular user sees only their own
        return [
            'bool' => [
                'must' => [
                    ['term' => ['entity_type' => $entityClass]],
                    ['term' => ['owner_id' => $userId]],
                ],
            ],
        ];
    }
}
