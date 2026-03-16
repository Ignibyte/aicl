<?php

namespace Aicl\Broadcasting;

/**
 * Static authorization helpers for WebSocket broadcast channels.
 *
 * Provides reusable authorization logic for private and presence channels
 * used throughout the AICL broadcasting system. Follows Spatie/Shield
 * permission format for entity-specific channel authorization.
 *
 * @see BaseBroadcastEvent  Base class for broadcast events
 */
class ChannelAuth
{
    /**
     * Authorize a user for an entity-specific private channel.
     *
     * Format: {entity_type}s.{entity_id}
     * Checks: user has ViewAny:{Entity} permission (Spatie/Shield format).
     */
    public static function entityChannel(mixed $user, string $entityClass, int|string $id): bool
    {
        $entity = $entityClass::find($id);

        if (! $entity) {
            return false;
        }

        $permission = 'ViewAny:'.class_basename($entityClass);

        return $user->can($permission);
    }

    /**
     * Authorize a user for their own private channel.
     *
     * Format: App.Models.User.{id}
     */
    public static function userChannel(mixed $user, int|string $id): bool
    {
        return (string) $user->getKey() === (string) $id;
    }

    /**
     * Authorize a user for a presence channel on an entity.
     *
     * Returns user data for the presence channel (name, avatar).
     *
     * @return array{id: int|string, name: string}|false
     */
    public static function presenceChannel(mixed $user, string $entityClass, int|string $id): array|false
    {
        if (! static::entityChannel($user, $entityClass, $id)) {
            return false;
        }

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
        ];
    }
}
