<?php

namespace Aicl\Traits;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityCreating;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityDeleting;
use Aicl\Events\EntityUpdated;
use Aicl\Events\EntityUpdating;
use Illuminate\Database\Eloquent\Model;

/**
 * Dispatches typed entity lifecycle events.
 *
 * Hooks into Eloquent model events to dispatch AICL's typed events
 * (EntityCreating, EntityCreated, etc.) alongside the standard Eloquent
 * events. This enables cross-cutting listeners that work on any entity
 * implementing HasEntityLifecycle.
 *
 * @mixin Model
 */
trait HasEntityEvents
{
    /**
     * Boot the trait by registering Eloquent model event listeners.
     *
     * Listens for creating, created, updating, updated, deleting, and deleted
     * events and dispatches the corresponding typed AICL entity events.
     */
    public static function bootHasEntityEvents(): void
    {
        static::creating(function ($model): void {
            EntityCreating::dispatch($model);
        });

        static::created(function ($model): void {
            EntityCreated::dispatch($model);
        });

        static::updating(function ($model): void {
            EntityUpdating::dispatch($model);
        });

        static::updated(function ($model): void {
            EntityUpdated::dispatch($model);
        });

        static::deleting(function ($model): void {
            EntityDeleting::dispatch($model);
        });

        static::deleted(function ($model): void {
            EntityDeleted::dispatch($model);
        });
    }
}
