<?php

namespace Aicl\Notifications\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface NotificationRecipientResolver
{
    /**
     * Resolve who should receive notifications for a given entity action.
     *
     * @param  Model  $entity  The entity that triggered the notification
     * @param  string  $action  The action (e.g., 'created', 'status_changed', 'assigned')
     * @return Collection<int, Model> The notifiable models
     */
    public function resolve(Model $entity, string $action): Collection;
}
