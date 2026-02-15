<?php

namespace Aicl\Models;

use Aicl\Database\Factories\NotificationLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string|null $type
 * @property string|null $notifiable_type
 * @property string|int|null $notifiable_id
 * @property string|null $sender_type
 * @property string|int|null $sender_id
 * @property array<int, string>|null $channels
 * @property array<string, string>|null $channel_status
 * @property array<string, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class NotificationLog extends Model
{
    /** @use HasFactory<NotificationLogFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'notification_logs';

    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'sender_type',
        'sender_id',
        'channels',
        'channel_status',
        'data',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'channel_status' => 'array',
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * The notifiable entity (typically a User).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The entity that triggered the notification (nullable for system notifications).
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to a specific user's notifications.
     *
     * @param  Builder<NotificationLog>  $query
     * @return Builder<NotificationLog>
     */
    public function scopeForUser(Builder $query, Model $user): Builder
    {
        return $query
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey());
    }

    /**
     * Scope to a specific notification type.
     *
     * @param  Builder<NotificationLog>  $query
     * @return Builder<NotificationLog>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to unread notifications.
     *
     * @param  Builder<NotificationLog>  $query
     * @return Builder<NotificationLog>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to notifications with at least one failed channel.
     *
     * @param  Builder<NotificationLog>  $query
     * @return Builder<NotificationLog>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('channel_status', 'LIKE', '%"failed"%');
    }

    /**
     * Get a human-readable label for the notification type.
     */
    public function getTypeLabelAttribute(): string
    {
        $class = $this->type;

        if (! $class) {
            return 'Unknown';
        }

        $basename = class_basename($class);

        return str($basename)
            ->replaceLast('Notification', '')
            ->headline()
            ->toString();
    }

    /**
     * Mark this notification log as read.
     */
    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark this notification log as unread.
     */
    public function markAsUnread(): void
    {
        if ($this->read_at) {
            $this->update(['read_at' => null]);
        }
    }

    protected static function newFactory(): NotificationLogFactory
    {
        return NotificationLogFactory::new();
    }
}
