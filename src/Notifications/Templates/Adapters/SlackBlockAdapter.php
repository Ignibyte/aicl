<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;

/**
 * Slack Block Kit adapter.
 *
 * Produces the same structure as SlackDriver::buildPayload().
 */
class SlackBlockAdapter implements ChannelFormatAdapter
{
    public function format(array $rendered, array $context): array
    {
        $title = $rendered['title'];
        $body = $rendered['body'];
        $actionUrl = $rendered['action_url'] ?? null;
        $color = $rendered['color'] ?? '#3b82f6';

        $payload = [
            'text' => $title,
            'attachments' => [
                [
                    'text' => $body,
                    'color' => $color,
                ],
            ],
        ];

        if ($actionUrl) {
            $actionText = $rendered['action_text'] ?? 'View Details';
            $payload['attachments'][0]['actions'] = [
                [
                    'type' => 'button',
                    'text' => $actionText,
                    'url' => $actionUrl,
                ],
            ];
        }

        return $payload;
    }

    public function channelType(): ChannelType
    {
        return ChannelType::Slack;
    }
}
