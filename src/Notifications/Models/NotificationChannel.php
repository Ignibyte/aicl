<?php

namespace Aicl\Notifications\Models;

use Aicl\Notifications\Enums\ChannelType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property ChannelType $type
 * @property array<string, mixed> $config
 * @property array<string, array{title: string, body: string}>|null $message_templates
 * @property array{max: int, period: string}|null $rate_limit
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class NotificationChannel extends Model
{
    use HasUuids;

    protected $table = 'notification_channels';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'config',
        'message_templates',
        'rate_limit',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'config' => 'encrypted:array',
            'message_templates' => 'array',
            'rate_limit' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<NotificationDeliveryLog, $this>
     */
    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(NotificationDeliveryLog::class, 'channel_id');
    }

    /**
     * Get a template for a specific notification class.
     *
     * Resolution order: exact class name, then '_default', then null.
     *
     * @return array{title: string, body: string}|null
     */
    public function getTemplate(string $notificationClass): ?array
    {
        $templates = $this->message_templates ?? [];

        if (isset($templates[$notificationClass])) {
            return $templates[$notificationClass];
        }

        if (isset($templates['_default'])) {
            return $templates['_default'];
        }

        return null;
    }

    /**
     * @param  Builder<NotificationChannel>  $query
     * @return Builder<NotificationChannel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<NotificationChannel>  $query
     * @return Builder<NotificationChannel>
     */
    public function scopeOfType(Builder $query, ChannelType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
