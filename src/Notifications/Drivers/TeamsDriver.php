<?php

declare(strict_types=1);

namespace Aicl\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * @codeCoverageIgnore External notification service
 */
class TeamsDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        $config = $channel->config;
        $webhookUrl = $config['webhook_url'] ?? '';

        $teamsPayload = $this->buildAdaptiveCard($payload);

        try {
            $response = Http::timeout(10)->post($webhookUrl, $teamsPayload);

            if ($response->successful()) {
                return DriverResult::success(response: ['status' => $response->status()]);
            }

            $retryable = $response->status() >= 500;

            return DriverResult::failure(
                error: "Teams webhook returned {$response->status()}: {$response->body()}",
                retryable: $retryable,
                response: ['status' => $response->status(), 'body' => $response->body()],
            );
        } catch (Throwable $e) {
            return DriverResult::failure(error: $e->getMessage());
        }
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['webhook_url'])) {
            $errors['webhook_url'] = 'Teams webhook URL is required.';
        } elseif (! filter_var($config['webhook_url'], FILTER_VALIDATE_URL)) {
            $errors['webhook_url'] = 'Webhook URL must be a valid URL.';
        }

        return $errors;
    }

    /**
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function configSchema(): array
    {
        return [
            'webhook_url' => ['type' => 'url', 'label' => 'Webhook URL', 'required' => true],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    protected function buildAdaptiveCard(array $payload): array
    {
        $title = $payload['title'] ?? 'Notification';
        $body = $payload['body'] ?? '';
        $actionUrl = $payload['action_url'] ?? null;

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
                'title' => $payload['action_text'] ?? 'View Details',
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
}
