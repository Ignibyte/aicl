<?php

namespace Aicl\Traits;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Records model changes to the activity log.
 *
 * Wraps spatie/laravel-activitylog with sensible defaults:
 * - Logs all attributes
 * - Only logs dirty (changed) attributes
 * - Skips empty changelogs
 *
 * Override getActivitylogOptions() in your model to customize.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasAuditTrail
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName): string {
                /** @var Model $this */
                $className = class_basename(static::class);

                return "{$className} was {$eventName}";
            });
    }
}
