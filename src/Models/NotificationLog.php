<?php

declare(strict_types=1);

namespace Aicl\Models;

use Aicl\Database\Factories\NotificationLogFactory;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Unified notification audit log model.
 *
 * Records every notification dispatched through AICL's NotificationDispatcher
 * and Laravel's native notification system (via NotificationSentLogger).
 * Tracks channel-level delivery status, supports read/unread state, and
 * provides scopes for filtering by user, type, and delivery status.
 *
 * @property string|null                $type
 * @property string|null                $notifiable_type
 * @property string|int|null            $notifiable_id
 * @property string|null                $sender_type
 * @property string|int|null            $sender_id
 * @property array<int, string>|null    $channels
 * @property array<string, string>|null $channel_status
 * @property array<string, mixed>|null  $data
 * @property Carbon|null                $read_at
 * @property Carbon|null                $created_at
 * @property Carbon|null                $updated_at
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
     *
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The entity that triggered the notification (nullable for system notifications).
     *
     * @return MorphTo<Model, $this>
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to a specific user's notifications.
     *
     * @param Builder<NotificationLog> $query
     *
     * @return Builder<NotificationLog>
     */
    public function scopeForUser(Builder $query, Model $user): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $query
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Scope to a specific notification type.
     *
     * @param Builder<NotificationLog> $query
     *
     * @return Builder<NotificationLog>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $query->where('type', $type);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Scope to unread notifications.
     *
     * @param Builder<NotificationLog> $query
     *
     * @return Builder<NotificationLog>
     */
    public function scopeUnread(Builder $query): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $query->whereNull('read_at');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Scope to notifications with at least one failed channel.
     *
     * Uses PostgreSQL jsonb_each_text() for efficient JSONB value matching
     * instead of LIKE which forces full text pattern matching on the column.
     *
     * @param Builder<NotificationLog> $query
     *
     * @return Builder<NotificationLog>
     */
    public function scopeFailed(Builder $query): Builder
    {
        /** @var Connection $connection */
        // @codeCoverageIgnoreStart — Untestable in unit context
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            return $query->whereRaw(
                "EXISTS (SELECT 1 FROM jsonb_each_text(channel_status::jsonb) AS x WHERE x.value = 'failed')"
            );
        }

        return $query->where('channel_status', 'LIKE', '%"failed"%');
        // @codeCoverageIgnoreEnd
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
        // @codeCoverageIgnoreStart — Untestable in unit context
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Mark this notification log as unread.
     */
    public function markAsUnread(): void
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        if ($this->read_at) {
            $this->update(['read_at' => null]);
            // @codeCoverageIgnoreEnd
        }
    }

    protected static function newFactory(): NotificationLogFactory
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return NotificationLogFactory::new();
        // @codeCoverageIgnoreEnd
    }
}
