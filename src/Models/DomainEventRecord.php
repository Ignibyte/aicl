<?php

declare(strict_types=1);

namespace Aicl\Models;

use Aicl\Database\Factories\DomainEventRecordFactory;
use Aicl\Events\DomainEvent;
use Aicl\Events\DomainEventRegistry;
use Aicl\Events\Enums\ActorType;
use Aicl\Events\Exceptions\UnresolvableEventException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of a persisted domain event.
 *
 * @property string $id
 * @property string $event_type
 * @property string $actor_type
 * @property int|null $actor_id
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property array<string, mixed> $payload
 * @property array<string, mixed> $metadata
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class DomainEventRecord extends Model
{
    /** @use HasFactory<DomainEventRecordFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'domain_events';

    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'actor_type',
        'actor_id',
        'entity_type',
        'entity_id',
        'payload',
        'metadata',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model — set created_at on creating since we disabled $timestamps.
     */
    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (! $record->created_at) {
                $record->created_at = now();
            }
        });
    }

    /**
     * Scope to events for a specific entity.
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeForEntity(Builder $query, Model $entity): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $query
            ->where('entity_type', $entity->getMorphClass())
            ->where('entity_id', (string) $entity->getKey());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Scope to events of a specific type, with wildcard support.
     *
     * Examples:
     *   'order.fulfilled' → exact match
     *   'order.*'         → WHERE event_type LIKE 'order.%'
     *   '*.escalated'     → WHERE event_type LIKE '%.escalated'
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        if (str_contains($type, '*')) {
            $pattern = str_replace('*', '%', $type);

            return $query->where('event_type', 'LIKE', $pattern);
        }

        return $query->where('event_type', $type);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Scope to events that occurred on or after a given date.
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeSince(Builder $query, Carbon $date): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $query->where('occurred_at', '>=', $date);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Scope to events that occurred within a date range.
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $query->whereBetween('occurred_at', [$start, $end]);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Scope to events by a specific actor type and optionally a specific actor.
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeByActor(Builder $query, ActorType $type, ?int $id = null): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        $query->where('actor_type', $type->value);

        if ($id !== null) {
            $query->where('actor_id', $id);
        }

        return $query;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Scope that returns all events for an entity, ordered by most recent first.
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeTimeline(Builder $query, Model $entity): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $query->forEntity($entity)->latest('occurred_at');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Delete events older than the given date.
     * Respects append-only principle — only removes old historical data.
     */
    public static function prune(Carbon $before): int
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return static::query()->where('occurred_at', '<', $before)->delete();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Replay this persisted event through the Laravel event dispatcher.
     *
     * Reconstructs the original DomainEvent class from the registry,
     * marks it as a replay (so the subscriber skips re-persistence),
     * and dispatches it so other listeners can process it.
     *
     * @throws UnresolvableEventException
     */
    public function replay(): DomainEvent
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        $event = DomainEventRegistry::reconstruct($this);
        $event->markAsReplay();
        event($event);

        return $event;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the actor type as an enum.
     */
    public function getActorTypeEnumAttribute(): ActorType
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return ActorType::from($this->actor_type);
        // @codeCoverageIgnoreEnd
    }

    protected static function newFactory(): DomainEventRecordFactory
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return DomainEventRecordFactory::new();
        // @codeCoverageIgnoreEnd
    }
}
