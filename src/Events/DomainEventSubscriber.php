<?php

declare(strict_types=1);

namespace Aicl\Events;

use Aicl\Models\DomainEventRecord;
use Illuminate\Events\Dispatcher;

/**
 * DomainEventSubscriber.
 */
class DomainEventSubscriber
{
    /**
     * Handle a domain event by persisting it to the domain_events table.
     *
     * Runs synchronously within the business transaction to guarantee
     * consistency. Broadcasting (if enabled) is handled separately by
     * Laravel's broadcast queue.
     */
    public function handleDomainEvent(DomainEvent $event): void
    {
        if ($event->isReplay()) {
            // @codeCoverageIgnoreStart — Event infrastructure
            return;
            // @codeCoverageIgnoreEnd
        }

        DomainEventRecord::create([
            'event_type' => $event->getEventType(),
            'actor_type' => $event->getActorType()->value,
            'actor_id' => $event->getActorId(),
            'entity_type' => $event->getEntityType(),
            'entity_id' => $event->getEntityId() !== null ? (string) $event->getEntityId() : null,
            'payload' => $event->toPayload(),
            'metadata' => $event->toMetadata(),
            'occurred_at' => $event->occurredAt,
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * Uses a wildcard listener with instanceof check to catch ALL
     * DomainEvent subclasses. Laravel dispatches object events by their
     * concrete class name, so a parent class mapping won't match subclasses.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen('*', function (string $eventName, array $data): void {
            if (isset($data[0]) && $data[0] instanceof DomainEvent) {
                $this->handleDomainEvent($data[0]);
            }
        });
    }
}
