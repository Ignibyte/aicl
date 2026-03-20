<?php

declare(strict_types=1);

namespace Aicl\Listeners;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Models\NotificationLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Queued listener that creates database notifications for entity lifecycle events.
 *
 * When an entity is created, updated, or deleted, this listener creates
 * DatabaseNotification records for the entity owner and super_admin users
 * (excluding the actor who triggered the event). Also logs each notification
 * to NotificationLog for audit trail.
 *
 * @see EntityCreated
 * @see EntityUpdated
 * @see EntityDeleted
 */
class EntityEventNotificationListener implements ShouldQueue
{
    /**
     * Handle entity created events.
     */
    public function handleCreated(EntityCreated $event): void
    {
        $this->createNotifications(
            entity: $event->entity,
            entityType: class_basename($event->entity),
            entityId: $event->entity->getKey(),
            entityName: $this->getEntityName($event->entity),
            action: 'created',
        );
    }

    /**
     * Handle entity updated events.
     */
    public function handleUpdated(EntityUpdated $event): void
    {
        $this->createNotifications(
            entity: $event->entity,
            entityType: class_basename($event->entity),
            entityId: $event->entity->getKey(),
            entityName: $this->getEntityName($event->entity),
            action: 'updated',
        );
    }

    /**
     * Handle entity deleted events.
     */
    public function handleDeleted(EntityDeleted $event): void
    {
        $this->createNotifications(
            entity: null,
            entityType: $event->entityType,
            entityId: $event->entityId,
            entityName: $event->entityType.' #'.$event->entityId,
            action: 'deleted',
        );
    }

    /**
     * Create database notifications for all relevant users except the actor.
     */
    protected function createNotifications(
        ?Model $entity,
        string $entityType,
        int|string $entityId,
        string $entityName,
        string $action,
    ): void {
        /** @var int|null $actorId */
        $actorId = auth()->check() ? (int) auth()->id() : null;
        $user = auth()->user();
        $actorName = $user ? $user->name : 'System';

        $recipients = $this->getRecipients($entity, $actorId);

        if ($recipients->isEmpty()) {
            return;
        }

        $data = [
            'title' => $this->getTitle($entityType, $entityName, $action),
            'body' => $this->getBody($entityType, $entityName, $action, $actorName),
            'icon' => $this->getIcon($action),
            'color' => $this->getColor($action),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
        ];

        if ($entity && $action !== 'deleted') {
            $data['action_url'] = $this->getEntityUrl($entity, $entityType);
            $data['action_text'] = "View {$entityType}";
        }

        $now = now();
        $actor = auth()->user();

        foreach ($recipients as $user) {
            DatabaseNotification::create([
                'id' => Str::uuid()->toString(),
                'type' => 'Aicl\\Notifications\\EntityEventNotification',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->getKey(),
                'data' => $data,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            NotificationLog::create([
                'type' => 'Aicl\\Notifications\\EntityEventNotification',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->getKey(),
                'sender_type' => $actor ? get_class($actor) : null,
                'sender_id' => $actorId,
                'channels' => ['database'],
                'channel_status' => ['database' => 'sent'],
                'data' => $data,
            ]);
        }
    }

    /**
     * Get users who should receive the notification.
     *
     * @return Collection<int, User>
     */
    protected function getRecipients(?Model $entity, ?int $actorId): Collection
    {
        $query = User::query();

        // Exclude the user who performed the action
        if ($actorId) {
            $query->where('id', '!=', $actorId);
        }

        // If entity has an owner, prioritize them
        if ($entity && method_exists($entity, 'owner') && $entity->owner_id) {
            return $query->where(function ($q) use ($entity) {
                $q->where('id', $entity->owner_id)
                    ->orWhereHas('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'super_admin');
                    });
            })->get();
        }

        // Default: notify admins only
        return $query->whereHas('roles', function ($roleQuery) {
            $roleQuery->where('name', 'super_admin');
        })->get();
    }

    /**
     * Extract a human-readable name from the entity model.
     *
     * Falls back to "ClassName #ID" if neither name nor title attributes exist.
     *
     * @param  Model  $entity  The entity instance
     */
    protected function getEntityName(Model $entity): string
    {
        if (isset($entity->name)) {
            return $entity->name;
        }

        if (isset($entity->title)) {
            return $entity->title;
        }

        return class_basename($entity).' #'.$entity->getKey();
    }

    /**
     * Generate the notification title based on the action type.
     *
     * @param  string  $entityType  The entity class basename
     * @param  string  $entityName  The entity display name
     * @param  string  $action  The lifecycle action (created, updated, deleted)
     */
    protected function getTitle(string $entityType, string $entityName, string $action): string
    {
        return match ($action) {
            'created' => "{$entityType} Created",
            'updated' => "{$entityType} Updated",
            'deleted' => "{$entityType} Deleted",
            default => "{$entityType} {$action}",
        };
    }

    /**
     * Generate the notification body message describing who did what.
     *
     * @param  string  $entityType  The entity class basename
     * @param  string  $entityName  The entity display name
     * @param  string  $action  The lifecycle action
     * @param  string  $actorName  Name of the user who performed the action
     */
    protected function getBody(string $entityType, string $entityName, string $action, string $actorName): string
    {
        return match ($action) {
            'created' => "{$actorName} created {$entityType} \"{$entityName}\".",
            'updated' => "{$actorName} updated {$entityType} \"{$entityName}\".",
            'deleted' => "{$actorName} deleted {$entityType} \"{$entityName}\".",
            default => "{$actorName} performed {$action} on {$entityType} \"{$entityName}\".",
        };
    }

    /**
     * Map an action to its corresponding Heroicon name.
     *
     * @param  string  $action  The lifecycle action
     * @return string Heroicon component name
     */
    protected function getIcon(string $action): string
    {
        return match ($action) {
            'created' => 'heroicon-o-plus-circle',
            'updated' => 'heroicon-o-pencil-square',
            'deleted' => 'heroicon-o-trash',
            default => 'heroicon-o-bell',
        };
    }

    /**
     * Map an action to its corresponding Filament notification color.
     *
     * @param  string  $action  The lifecycle action
     * @return string Filament color name
     */
    protected function getColor(string $action): string
    {
        return match ($action) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            default => 'primary',
        };
    }

    /**
     * Build the Filament admin URL for viewing the entity.
     *
     * @param  Model  $entity  The entity model
     * @param  string  $entityType  The entity class basename
     * @return string|null The route URL, or null if the route does not exist
     */
    protected function getEntityUrl(Model $entity, string $entityType): ?string
    {
        $slug = Str::plural(Str::kebab($entityType));

        try {
            return route("filament.admin.resources.{$slug}.view", ['record' => $entity]);
        } catch (\Exception) {
            return null;
        }
    }
}
