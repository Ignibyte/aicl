<?php

namespace Aicl\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;

/**
 * PagerDuty Events API v2 adapter.
 *
 * Produces the same structure as PagerDutyDriver::buildPayload().
 */
class PagerDutyAdapter implements ChannelFormatAdapter
{
    public function format(array $rendered, array $context): array
    {
        $title = $rendered['title'];
        $body = $rendered['body'];
        $color = $rendered['color'] ?? 'info';

        $channel = $context['channel'] ?? null;
        $routingKey = '';
        if ($channel && isset($channel->config['routing_key'])) {
            $routingKey = $channel->config['routing_key'];
        }

        $severity = $this->mapSeverity($color);

        return [
            'routing_key' => $routingKey,
            'event_action' => 'trigger',
            'payload' => [
                'summary' => "{$title}: {$body}",
                'severity' => $severity,
                'source' => config('app.name', 'AICL'),
                'component' => $context['entity_type'] ?? 'application',
                'custom_details' => $rendered,
            ],
        ];
    }

    public function channelType(): ChannelType
    {
        return ChannelType::PagerDuty;
    }

    /**
     * Map a color value to a PagerDuty severity level.
     */
    protected function mapSeverity(string $color): string
    {
        return match ($color) {
            'danger' => 'critical',
            'warning' => 'warning',
            'success' => 'info',
            default => 'info',
        };
    }
}
