<?php

// PATTERN: Observer extends BaseObserver which handles common lifecycle hooks.
// PATTERN: Methods receive Model type hint, then use @var docblock for the specific type.
// PATTERN: Use NotificationDispatcher (not $user->notify()) for notifications.
// PATTERN: Use activity() helper for audit logging.

namespace App\Observers;

use Aicl\Notifications\ProjectAssignedNotification;
use Aicl\Notifications\ProjectStatusChangedNotification;
use Aicl\Services\NotificationDispatcher;
use Aicl\States\ProjectState;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

class ProjectObserver extends BaseObserver
{
    // PATTERN: updating() fires BEFORE the database write — use for logging old values.
    public function updating(Model $model): void
    {
        /** @var Project $model */
        if ($model->isDirty('status')) {
            activity()
                ->performedOn($model)
                ->withProperties([
                    'old_status' => $model->getOriginal('status'),
                    'new_status' => (string) $model->status,
                ])
                ->log("Project status changed to {$model->status->label()}");
        }
    }

    // PATTERN: updated() fires AFTER the database write — use for notifications.
    public function updated(Model $model): void
    {
        /** @var Project $model */
        // PATTERN: wasChanged() checks if a column was modified in this save.
        if ($model->wasChanged('owner_id') && $model->owner_id) {
            $this->notifyOwnerAssignment($model);
        }

        if ($model->wasChanged('status')) {
            $this->notifyStatusChange($model);
        }
    }

    public function created(Model $model): void
    {
        /** @var Project $model */
        activity()
            ->performedOn($model)
            ->log("Project \"{$model->name}\" was created");

        if ($model->owner_id) {
            $this->notifyOwnerAssignment($model);
        }
    }

    public function deleted(Model $model): void
    {
        /** @var Project $model */
        activity()
            ->performedOn($model)
            ->log("Project \"{$model->name}\" was deleted");
    }

    // PATTERN: Extract notification logic into protected methods.
    protected function notifyOwnerAssignment(Project $project): void
    {
        $owner = $project->owner;
        $assignedBy = auth()->user();

        if (! $owner || ! $assignedBy) {
            return;
        }

        // PATTERN: Don't notify users about their own actions.
        if ($owner->id === $assignedBy->id) {
            return;
        }

        // PATTERN: Use NotificationDispatcher to create a NotificationLog + dispatch per channel.
        app(NotificationDispatcher::class)->send(
            $owner,
            new ProjectAssignedNotification($project, $assignedBy),
            $assignedBy,
        );
    }

    protected function notifyStatusChange(Project $project): void
    {
        $owner = $project->owner;

        if (! $owner) {
            return;
        }

        // PATTERN: getOriginal() may return a string or State object depending on timing.
        $previousStatus = $project->getOriginal('status');
        $newStatus = $project->status;

        if (! $previousStatus instanceof ProjectState) {
            $previousStatusClass = '\\Aicl\\States\\'.ucfirst($previousStatus);
            if (class_exists($previousStatusClass)) {
                $previousStatus = new $previousStatusClass($project);
            } else {
                return;
            }
        }

        $changedBy = auth()->user();

        if ($changedBy && $owner->id === $changedBy->id) {
            return;
        }

        app(NotificationDispatcher::class)->send(
            $owner,
            new ProjectStatusChangedNotification($project, $previousStatus, $newStatus, $changedBy),
            $changedBy,
        );
    }
}
