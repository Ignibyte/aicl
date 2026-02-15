<?php

namespace Aicl\Events;

use Aicl\Events\Enums\ActorType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class DomainEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Dot-notation event type identifier (e.g., 'order.fulfilled').
     * Must be declared by each concrete subclass.
     */
    public static string $eventType;

    public string $eventId;

    public Carbon $occurredAt;

    protected ActorType $actorType;

    protected ?int $actorId;

    public ?Model $entity = null;

    protected bool $replaying = false;

    public function __construct(?ActorType $actorType = null, ?int $actorId = null)
    {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = Carbon::now();

        if ($actorType !== null) {
            $this->actorType = $actorType;
            $this->actorId = $actorId;
        } else {
            $this->resolveActor();
        }
    }

    public function getEventType(): string
    {
        return static::$eventType;
    }

    public function getActorType(): ActorType
    {
        return $this->actorType;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    /**
     * Get the morph type for the associated entity.
     */
    public function getEntityType(): ?string
    {
        return $this->entity ? $this->entity->getMorphClass() : null;
    }

    /**
     * Get the primary key of the associated entity.
     */
    public function getEntityId(): int|string|null
    {
        return $this->entity?->getKey();
    }

    /**
     * Set the entity this event relates to.
     */
    public function forEntity(Model $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * Get the entity this event relates to.
     */
    public function getEntity(): ?Model
    {
        return $this->entity;
    }

    /**
     * Event-specific business data for persistence.
     * Override in subclasses to provide structured payload.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [];
    }

    /**
     * Cross-cutting metadata (IP, request ID, user agent, etc.).
     *
     * @return array<string, mixed>
     */
    public function toMetadata(): array
    {
        $metadata = [];

        if (! app()->runningInConsole()) {
            $metadata['ip'] = request()->ip();
            $metadata['user_agent'] = request()->userAgent();
            $metadata['request_id'] = request()->header('X-Request-ID');
        }

        return array_filter($metadata);
    }

    public function isReplay(): bool
    {
        return $this->replaying;
    }

    /**
     * Mark this event as a replay (prevents re-persistence).
     */
    public function markAsReplay(): static
    {
        $this->replaying = true;

        return $this;
    }

    /**
     * Register this event type in the DomainEventRegistry.
     */
    public static function register(): void
    {
        DomainEventRegistry::register(static::$eventType, static::class);
    }

    /**
     * Auto-resolve actor from current auth/console context.
     */
    protected function resolveActor(): void
    {
        if (auth()->check()) {
            $this->actorType = ActorType::User;
            $this->actorId = auth()->id();
        } elseif (app()->runningInConsole()) {
            $this->actorType = ActorType::System;
            $this->actorId = null;
        } else {
            $this->actorType = ActorType::System;
            $this->actorId = null;
        }
    }
}
