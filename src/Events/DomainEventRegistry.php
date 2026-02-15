<?php

namespace Aicl\Events;

use Aicl\Events\Enums\ActorType;
use Aicl\Events\Exceptions\UnresolvableEventException;
use Aicl\Models\DomainEventRecord;

class DomainEventRegistry
{
    /**
     * Map of event type strings to their class names.
     *
     * @var array<string, class-string<DomainEvent>>
     */
    protected static array $registry = [];

    /**
     * Register an event type → class mapping.
     *
     * @param  class-string<DomainEvent>  $className
     */
    public static function register(string $eventType, string $className): void
    {
        static::$registry[$eventType] = $className;
    }

    /**
     * Check if an event type has a registered class.
     */
    public static function has(string $eventType): bool
    {
        return isset(static::$registry[$eventType]);
    }

    /**
     * Resolve the class name for an event type.
     *
     * @return class-string<DomainEvent>
     *
     * @throws UnresolvableEventException
     */
    public static function resolve(string $eventType): string
    {
        if (! static::has($eventType)) {
            throw UnresolvableEventException::forType($eventType);
        }

        return static::$registry[$eventType];
    }

    /**
     * Reconstruct a DomainEvent from a persisted record.
     *
     * Creates a minimal instance of the registered event class
     * with actor context from the stored record.
     *
     * @throws UnresolvableEventException
     */
    public static function reconstruct(DomainEventRecord $record): DomainEvent
    {
        $className = static::resolve($record->event_type);

        $reflection = new \ReflectionClass($className);
        $event = $reflection->newInstanceWithoutConstructor();

        // Hydrate the base properties
        $baseReflection = new \ReflectionClass(DomainEvent::class);

        $eventIdProp = $baseReflection->getProperty('eventId');
        $eventIdProp->setValue($event, $record->id);

        $occurredAtProp = $baseReflection->getProperty('occurredAt');
        $occurredAtProp->setValue($event, $record->occurred_at);

        $actorTypeProp = $baseReflection->getProperty('actorType');
        $actorTypeProp->setValue($event, ActorType::from($record->actor_type));

        $actorIdProp = $baseReflection->getProperty('actorId');
        $actorIdProp->setValue($event, $record->actor_id);

        return $event;
    }

    /**
     * Get all registered event types.
     *
     * @return array<string, class-string<DomainEvent>>
     */
    public static function all(): array
    {
        return static::$registry;
    }

    /**
     * Clear all registrations (for testing).
     */
    public static function flush(): void
    {
        static::$registry = [];
    }
}
