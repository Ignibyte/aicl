<?php

declare(strict_types=1);

namespace Aicl\Services;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\BaseNotification;
use Aicl\Notifications\ChannelRateLimiter;
use Aicl\Notifications\Contracts\HasExternalChannels;
use Aicl\Notifications\Contracts\NotificationChannelResolver;
use Aicl\Notifications\Contracts\NotificationRecipientResolver;
use Aicl\Notifications\DriverRegistry;
use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Events\NotificationDispatched;
use Aicl\Notifications\Events\NotificationSending;
use Aicl\Notifications\Jobs\RetryNotificationDelivery;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use Aicl\Notifications\Templates\MessageTemplateRenderer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use Throwable;

/**
 * Central notification dispatch service with logging and external channel support.
 *
 * Orchestrates sending notifications through Laravel's built-in channels (mail,
 * database, broadcast) as well as external channels (Slack, Teams, PagerDuty,
 * SMS, webhooks) via the DriverRegistry. Each dispatch is logged to NotificationLog,
 * and external deliveries are tracked in NotificationDeliveryLog with rate limiting
 * and retry support.
 *
 * Fires NotificationSending (cancellable) and NotificationDispatched events.
 * Supports optional channel and recipient resolvers for dynamic routing.
 *
 * @see DriverRegistry  Registry of external channel drivers
 * @see ChannelRateLimiter  Per-channel rate limiting
 * @see BaseNotification  Base class for AICL notifications
 *
 * @codeCoverageIgnore Service integration
 */
class NotificationDispatcher
{
    /**
     * @param  DriverRegistry  $driverRegistry  Registry of external channel drivers
     * @param  ChannelRateLimiter  $rateLimiter  Per-channel rate limiter
     * @param  NotificationChannelResolver|null  $channelResolver  Optional resolver for dynamic channel selection
     * @param  NotificationRecipientResolver|null  $recipientResolver  Optional resolver for dynamic recipients
     */
    public function __construct(
        protected DriverRegistry $driverRegistry,
        protected ChannelRateLimiter $rateLimiter,
        protected ?NotificationChannelResolver $channelResolver = null,
        protected ?NotificationRecipientResolver $recipientResolver = null,
    ) {}

    /**
     * Send a notification to a single notifiable and log it.
     */
    public function send(
        mixed $notifiable,
        BaseNotification $notification,
        ?Model $sender = null,
    ): NotificationLog {
        $sendingEvent = new NotificationSending($notification, $notifiable, $sender);
        event($sendingEvent);

        if ($sendingEvent->cancelled) {
            return NotificationLog::create([
                'type' => get_class($notification),
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->getKey(),
                'sender_type' => $sender ? get_class($sender) : null,
                'sender_id' => $sender?->getKey(),
                'channels' => [],
                'channel_status' => ['_cancelled' => 'cancelled'],
                'data' => $notification->toDatabase($notifiable),
            ]);
        }

        $channels = $notification->via($notifiable);

        $log = NotificationLog::create([
            'type' => get_class($notification),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey(),
            'sender_type' => $sender ? get_class($sender) : null,
            'sender_id' => $sender?->getKey(),
            'channels' => $channels,
            'channel_status' => $this->initChannelStatus($channels),
            'data' => $notification->toDatabase($notifiable),
        ]);

        $channelStatus = [];

        foreach ($channels as $channel) {
            try {
                $notifiable->notify(
                    (clone $notification)->onlyVia($channel)
                );
                $channelStatus[$channel] = 'sent';
            } catch (Throwable $e) {
                $channelStatus[$channel] = 'failed';
                report($e);
            }
        }

        $log->update(['channel_status' => $channelStatus]);

        $externalChannels = $this->resolveExternalChannels($notification, $notifiable);

        foreach ($externalChannels as $externalChannel) {
            $this->dispatchToExternalChannel($log, $externalChannel, $notification, $notifiable);
        }

        event(new NotificationDispatched($notification, $notifiable, $log));

        return $log;
    }

    /**
     * Send a notification to many notifiables and log each.
     *
     * @param  Collection<int, Model>  $notifiables
     * @return Collection<int, NotificationLog>
     */
    public function sendToMany(
        Collection $notifiables,
        BaseNotification $notification,
        ?Model $sender = null,
    ): Collection {
        return $notifiables->map(
            fn (Model $notifiable) => $this->send($notifiable, $notification, $sender)
        );
    }

    /**
     * Dispatch to an external channel driver with rate limiting.
     */
    protected function dispatchToExternalChannel(
        NotificationLog $log,
        NotificationChannel $channel,
        BaseNotification $notification,
        object $notifiable,
    ): NotificationDeliveryLog {
        $rawPayload = $notification->toDatabase($notifiable);

        $renderer = app(MessageTemplateRenderer::class);
        $template = $renderer->resolveTemplate($channel, get_class($notification));

        if ($template) {
            $context = $this->buildTemplateContext($notification, $notifiable, $channel, $rawPayload);
            $payload = $renderer->renderForChannel($template, $context, $channel->type);
        } else {
            $payload = $rawPayload;
        }

        $deliveryLog = NotificationDeliveryLog::create([
            'notification_log_id' => $log->id,
            'channel_id' => $channel->id,
            'status' => DeliveryStatus::Pending,
            'attempt_count' => 0,
            'payload' => $payload,
        ]);

        if (! $this->rateLimiter->attempt($channel)) {
            $availableIn = $this->rateLimiter->availableIn($channel);
            $deliveryLog->update([
                'status' => DeliveryStatus::RateLimited,
                'next_retry_at' => now()->addSeconds($availableIn),
            ]);
            RetryNotificationDelivery::dispatch($deliveryLog->id)
                ->delay($availableIn);

            return $deliveryLog;
        }

        RetryNotificationDelivery::dispatch($deliveryLog->id);

        return $deliveryLog;
    }

    /**
     * Resolve which external NotificationChannel models should receive this notification.
     *
     * @return Collection<int, NotificationChannel>
     */
    protected function resolveExternalChannels(
        BaseNotification $notification,
        object $notifiable,
    ): Collection {
        if ($this->channelResolver) {
            return $this->channelResolver->resolve($notification, $notifiable);
        }

        if ($notification instanceof HasExternalChannels) {
            return $notification->externalChannels();
        }

        return collect();
    }

    /**
     * Initialize channel status as pending for all channels.
     *
     * @param  array<int, string>  $channels
     * @return array<string, string>
     */
    protected function initChannelStatus(array $channels): array
    {
        $status = [];

        foreach ($channels as $channel) {
            $status[$channel] = 'pending';
        }

        return $status;
    }

    /**
     * Build the template rendering context from notification components.
     *
     * @param  array<string, mixed>  $rawPayload
     * @return array<string, mixed>
     */
    protected function buildTemplateContext(
        BaseNotification $notification,
        object $notifiable,
        NotificationChannel $channel,
        array $rawPayload,
    ): array {
        $context = array_merge($rawPayload, [
            'recipient' => $notifiable,
            'channel' => $channel,
        ]);

        $ref = new ReflectionClass($notification);

        foreach ($ref->getConstructor()?->getParameters() ?? [] as $param) {
            if ($param->isPromoted()) {
                $value = $notification->{$param->getName()};
                if ($value instanceof Model) {
                    $context['model'] = $value;
                    break;
                }
            }
        }

        foreach ($ref->getConstructor()?->getParameters() ?? [] as $param) {
            if ($param->isPromoted()) {
                $value = $notification->{$param->getName()};
                if ($value instanceof Authenticatable) {
                    $context['user'] = $value;
                    break;
                }
            }
        }

        if (! isset($context['user']) && auth()->check()) {
            $context['user'] = auth()->user();
        }

        return $context;
    }
}
