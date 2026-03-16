<?php

namespace Aicl\Swoole\Cache;

use Aicl\Swoole\SwooleCache;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;

/**
 * Wires SwooleCache as L1 cache for notification badge counts.
 *
 * Caches per-user unread notification counts so Filament's navigation
 * badge renders without a DB query per page load. Populated lazily
 * on first access per user. Invalidated when notifications are
 * created, updated (read/unread), or deleted.
 */
class NotificationBadgeCacheManager
{
    public const TABLE_NAME = 'notification_badges';

    public const TABLE_ROWS = 1000;

    public const TABLE_TTL = 60;

    public const TABLE_VALUE_SIZE = 100;

    /**
     * Register the notification badge cache table and invalidation listeners.
     */
    public static function register(): void
    {
        static::registerTable();
        static::registerInvalidationListeners();
    }

    /**
     * Get the cached unread notification count for a user, or compute and cache it.
     *
     * Returns the count as a string for Filament's getNavigationBadge(),
     * or null if the count is zero.
     */
    public static function getBadge(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        $key = "user:{$userId}";

        if (SwooleCache::isAvailable()) {
            $cached = SwooleCache::get(static::TABLE_NAME, $key);

            if ($cached !== null) {
                return $cached['count'] > 0 ? (string) $cached['count'] : null;
            }
        }

        $count = static::computeUnreadCount($userId);

        if (SwooleCache::isAvailable()) {
            SwooleCache::set(static::TABLE_NAME, $key, ['count' => $count]);
        }

        return $count > 0 ? (string) $count : null;
    }

    /** Memoized User morph class — avoids instantiating a new User per call. */
    private static ?string $userMorphClass = null;

    /**
     * Compute the unread notification count for a user from the database.
     */
    public static function computeUnreadCount(int $userId): int
    {
        self::$userMorphClass ??= (new User)->getMorphClass();

        return DatabaseNotification::query()
            ->where('notifiable_type', self::$userMorphClass)
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Invalidate the badge cache for a specific user.
     */
    public static function invalidateForUser(int|string $userId): void
    {
        SwooleCache::forget(static::TABLE_NAME, "user:{$userId}");
    }

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
     * Register event-driven invalidation listeners.
     *
     * Listens to DatabaseNotification created/updated/deleted events to
     * invalidate the affected user's badge cache.
     */
    protected static function registerInvalidationListeners(): void
    {
        $model = DatabaseNotification::class;

        $invalidate = function (DatabaseNotification $notification): void {
            if ($notification->notifiable_id) {
                static::invalidateForUser($notification->notifiable_id);
            }
        };

        Event::listen("eloquent.created: {$model}", $invalidate);
        Event::listen("eloquent.updated: {$model}", $invalidate);
        Event::listen("eloquent.deleted: {$model}", $invalidate);
    }
}
