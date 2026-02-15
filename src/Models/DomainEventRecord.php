<?php

namespace Aicl\Models;

use Aicl\Database\Factories\DomainEventRecordFactory;
use Aicl\Events\DomainEvent;
use Aicl\Events\DomainEventRegistry;
use Aicl\Events\Enums\ActorType;
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
        return $query
            ->where('entity_type', $entity->getMorphClass())
            ->where('entity_id', (string) $entity->getKey());
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
        if (str_contains($type, '*')) {
            $pattern = str_replace('*', '%', $type);

            return $query->where('event_type', 'LIKE', $pattern);
        }

        return $query->where('event_type', $type);
    }

    /**
     * Scope to events that occurred on or after a given date.
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeSince(Builder $query, Carbon $date): Builder
    {
        return $query->where('occurred_at', '>=', $date);
    }

    /**
     * Scope to events that occurred within a date range.
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }

    /**
     * Scope to events by a specific actor type and optionally a specific actor.
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeByActor(Builder $query, ActorType $type, ?int $id = null): Builder
    {
        $query->where('actor_type', $type->value);

        if ($id !== null) {
            $query->where('actor_id', $id);
        }

        return $query;
    }

    /**
     * Scope that returns all events for an entity, ordered by most recent first.
     *
     * @param  Builder<DomainEventRecord>  $query
     * @return Builder<DomainEventRecord>
     */
    public function scopeTimeline(Builder $query, Model $entity): Builder
    {
        return $query->forEntity($entity)->latest('occurred_at');
    }

    /**
     * Delete events older than the given date.
     * Respects append-only principle — only removes old historical data.
     */
    public static function prune(Carbon $before): int
    {
        return static::query()->where('occurred_at', '<', $before)->delete();
    }

    /**
     * Replay this persisted event through the Laravel event dispatcher.
     *
     * Reconstructs the original DomainEvent class from the registry,
     * marks it as a replay (so the subscriber skips re-persistence),
     * and dispatches it so other listeners can process it.
     *
     * @throws \Aicl\Events\Exceptions\UnresolvableEventException
     */
    public function replay(): DomainEvent
    {
        $event = DomainEventRegistry::reconstruct($this);
        $event->markAsReplay();
        event($event);

        return $event;
    }

    /**
     * Get the actor type as an enum.
     */
    public function getActorTypeEnumAttribute(): ActorType
    {
        return ActorType::from($this->actor_type);
    }

    protected static function newFactory(): DomainEventRecordFactory
    {
        return DomainEventRecordFactory::new();
    }
}
