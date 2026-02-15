<?php

namespace Aicl\Notifications\Models;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $notification_log_id
 * @property string $channel_id
 * @property DeliveryStatus $status
 * @property int $attempt_count
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $response
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property \Illuminate\Support\Carbon|null $next_retry_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class NotificationDeliveryLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'notification_delivery_logs';

    protected $attributes = [
        'attempt_count' => 0,
    ];

    protected $fillable = [
        'notification_log_id',
        'channel_id',
        'status',
        'attempt_count',
        'payload',
        'response',
        'error_message',
        'sent_at',
        'delivered_at',
        'failed_at',
        'next_retry_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'payload' => 'array',
            'response' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->created_at) {
                $model->created_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<NotificationLog, $this>
     */
    public function notificationLog(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class, 'notification_log_id');
    }

    /**
     * @return BelongsTo<NotificationChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'channel_id');
    }

    /**
     * @param  Builder<NotificationDeliveryLog>  $query
     * @return Builder<NotificationDeliveryLog>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::Pending);
    }

    /**
     * @param  Builder<NotificationDeliveryLog>  $query
     * @return Builder<NotificationDeliveryLog>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::Failed);
    }

    /**
     * Retryable: failed, next_retry_at in the past, under max attempts.
     *
     * @param  Builder<NotificationDeliveryLog>  $query
     * @return Builder<NotificationDeliveryLog>
     */
    public function scopeRetryable(Builder $query): Builder
    {
        $maxAttempts = config('aicl.notifications.retry.max_attempts', 5);

        return $query
            ->where('status', DeliveryStatus::Failed)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->where('attempt_count', '<', $maxAttempts);
    }

    /**
     * @param  Builder<NotificationDeliveryLog>  $query
     * @return Builder<NotificationDeliveryLog>
     */
    public function scopeForChannel(Builder $query, NotificationChannel $channel): Builder
    {
        return $query->where('channel_id', $channel->id);
    }
}
