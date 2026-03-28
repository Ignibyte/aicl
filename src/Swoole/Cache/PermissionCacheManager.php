<?php

declare(strict_types=1);

namespace Aicl\Swoole\Cache;

use Aicl\Swoole\SwooleCache;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

/**
 * Wires SwooleCache as L1 cache for Spatie permission checks.
 *
 * Registers a Gate::before() interceptor that checks SwooleCache for
 * pre-computed per-user permission sets before falling through to
 * Spatie's normal Redis/PG path. Invalidation is event-driven via
 * Spatie's RoleAttached/RoleDetached/PermissionAttached/PermissionDetached
 * events, with a TTL safety net.
 */
class PermissionCacheManager
{
    public const TABLE_NAME = 'permissions';

    public const TABLE_ROWS = 2000;

    public const TABLE_TTL = 300;

    public const TABLE_VALUE_SIZE = 5000;

    /**
     * Register the permission cache table, Gate interceptor, and invalidation listeners.
     *
     * Enables Spatie's permission events (RoleAttached, etc.) if not already
     * enabled — these are required for cache invalidation.
     */
    public static function register(): void
    {
        // Ensure Spatie events are enabled for cache invalidation
        config(['permission.events_enabled' => true]);

        static::registerTable();
        static::registerGateInterceptor();
        static::registerInvalidationListeners();
    }

    /**
     * Build the permission cache entry for a user.
     *
     * @return array{permissions: list<string>, roles: list<string>, super_admin: bool}
     */
    public static function buildCacheForUser(Authorizable $user): array
    {
        // Guard: $user must implement Spatie HasRoles (not guaranteed by Authorizable contract)
        // @codeCoverageIgnoreStart — Swoole runtime
        if (! method_exists($user, 'getAllPermissions') || ! method_exists($user, 'roles')) { // @phpstan-ignore function.alreadyNarrowedType, function.alreadyNarrowedType
            return [
                'permissions' => [],
                'roles' => [],
                'super_admin' => false,
            ];
            // @codeCoverageIgnoreEnd
        }

        /** @var User $user */
        /** @var list<string> $roles */
        // @codeCoverageIgnoreStart — Swoole runtime
        $roles = $user->roles->pluck('name')->toArray();
        /** @var list<string> $permissions */
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return [
            'permissions' => $permissions,
            'roles' => $roles,
            'super_admin' => in_array('super_admin', $roles, true),
        ];
        // @codeCoverageIgnoreEnd
    }

    /**
     * Register the SwooleCache table for permissions.
     */
    protected static function registerTable(): void
    {
        SwooleCache::register(
            static::TABLE_NAME,
            rows: static::TABLE_ROWS,
            ttl: static::TABLE_TTL,
            valueSize: static::TABLE_VALUE_SIZE,
        );
    }

    /**
     * Register the Gate::before() interceptor.
     *
     * Checks SwooleCache for the user's pre-computed permission set.
     * On cache miss, builds and stores the set from Spatie's normal path.
     * Returns true for super_admin or matched permissions, null otherwise
     * to allow policies and other Gate callbacks to evaluate.
     */
    protected static function registerGateInterceptor(): void
    {
        Gate::before(function (Authorizable $user, string $ability) {
            if (! SwooleCache::isAvailable()) {
                return null;
            }

            // @codeCoverageIgnoreStart — Swoole runtime
            if (! method_exists($user, 'getAuthIdentifier')) { // @phpstan-ignore function.alreadyNarrowedType
                return null;
            }

            $key = 'user:'.$user->getAuthIdentifier();
            $cached = SwooleCache::get(static::TABLE_NAME, $key);

            if ($cached === null) {
                $cached = static::buildCacheForUser($user);
                SwooleCache::set(static::TABLE_NAME, $key, $cached);
            }

            if ($cached['super_admin'] ?? false) {
                return true;
            }

            if (in_array($ability, $cached['permissions'] ?? [], true)) {
                return true;
                // @codeCoverageIgnoreEnd
            }

            // Return null to let Gate/Policy handle — the user may have
            // access via a policy method (e.g., owner-based access)
            // @codeCoverageIgnoreStart — Swoole runtime
            return null;
            // @codeCoverageIgnoreEnd
        });
    }

    /**
     * Register event-driven invalidation listeners.
     *
     * Per-user invalidation on role/permission attach/detach.
     * Full table flush on global permission/role definition changes.
     */
    protected static function registerInvalidationListeners(): void
    {
        // Per-user invalidation when roles change
        SwooleCache::invalidateOn(
            static::TABLE_NAME,
            RoleAttached::class,
            fn (RoleAttached $e) => 'user:'.$e->model->getKey(),
        );

        SwooleCache::invalidateOn(
            static::TABLE_NAME,
            RoleDetached::class,
            fn (RoleDetached $e) => 'user:'.$e->model->getKey(),
        );

        // Per-user invalidation when direct permissions change
        SwooleCache::invalidateOn(
            static::TABLE_NAME,
            PermissionAttached::class,
            fn (PermissionAttached $e) => 'user:'.$e->model->getKey(),
        );

        SwooleCache::invalidateOn(
            static::TABLE_NAME,
            PermissionDetached::class,
            fn (PermissionDetached $e) => 'user:'.$e->model->getKey(),
        );

        // Global flush when permission/role definitions change
        $permissionModel = config('permission.models.permission');
        $roleModel = config('permission.models.role');

        Event::listen("eloquent.created: {$permissionModel}", fn () => SwooleCache::flush(static::TABLE_NAME));
        Event::listen("eloquent.deleted: {$permissionModel}", fn () => SwooleCache::flush(static::TABLE_NAME));
        Event::listen("eloquent.updated: {$roleModel}", fn () => SwooleCache::flush(static::TABLE_NAME));
        Event::listen("eloquent.deleted: {$roleModel}", fn () => SwooleCache::flush(static::TABLE_NAME));
    }
}
