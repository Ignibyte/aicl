<?php

declare(strict_types=1);

namespace Aicl\Observers;

use Illuminate\Database\Eloquent\Model;

/**
 * Base observer for AICL entities.
 *
 * Provides hook methods for all Eloquent lifecycle events.
 * AI-generated observers extend this class and override only the
 * methods they need — following the Drupal "hook_entity_*" pattern
 * mapped to Laravel Observers.
 *
 * Each method receives the model instance and can modify it (for
 * "before" hooks like creating/updating) or react to changes
 * (for "after" hooks like created/updated).
 *
 * Register observers in AppServiceProvider or entity-specific
 * service providers:
 *
 *     Project::observe(ProjectObserver::class);
 */
abstract class BaseObserver
{
    public function creating(Model $model): void {}

    public function created(Model $model): void {}

    public function updating(Model $model): void {}

    public function updated(Model $model): void {}

    public function saving(Model $model): void {}

    public function saved(Model $model): void {}

    public function deleting(Model $model): void {}

    public function deleted(Model $model): void {}

    public function restoring(Model $model): void {}

    public function restored(Model $model): void {}

    public function forceDeleting(Model $model): void {}

    public function forceDeleted(Model $model): void {}
}
