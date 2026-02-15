<?php

namespace Aicl\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;

/**
 * Microsoft Teams Adaptive Card adapter.
 *
 * Produces the same structure as TeamsDriver::buildAdaptiveCard().
 */
class TeamsCardAdapter implements ChannelFormatAdapter
{
    public function format(array $rendered, array $context): array
    {
        $title = $rendered['title'];
        $body = $rendered['body'];
        $actionUrl = $rendered['action_url'] ?? null;

        $cardBody = [
            [
                'type' => 'TextBlock',
                'text' => $title,
                'weight' => 'Bolder',
                'size' => 'Medium',
            ],
            [
                'type' => 'TextBlock',
                'text' => $body,
                'wrap' => true,
            ],
        ];

        $actions = [];
        if ($actionUrl) {
            $actions[] = [
                'type' => 'Action.OpenUrl',
                'title' => $rendered['action_text'] ?? 'View Details',
                'url' => $actionUrl,
            ];
        }

        return [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => $cardBody,
                        'actions' => $actions,
                    ],
                ],
            ],
        ];
    }

    public function channelType(): ChannelType
    {
        return ChannelType::Teams;
    }
}
