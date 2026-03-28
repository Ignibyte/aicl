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
class SlackDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        $config = $channel->config;
        $webhookUrl = $config['webhook_url'] ?? '';

        $slackPayload = $this->buildPayload($payload, $config);

        try {
            $response = Http::timeout(10)->post($webhookUrl, $slackPayload);

            if ($response->successful()) {
                return DriverResult::success(response: ['status' => $response->status()]);
            }

            $retryable = $response->status() >= 500;

            return DriverResult::failure(
                error: "Slack webhook returned {$response->status()}: {$response->body()}",
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
            $errors['webhook_url'] = 'Webhook URL is required.';
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
            'channel' => ['type' => 'string', 'label' => 'Channel Override', 'required' => false],
            'username' => ['type' => 'string', 'label' => 'Bot Username', 'required' => false],
            'icon_emoji' => ['type' => 'string', 'label' => 'Icon Emoji', 'required' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function buildPayload(array $payload, array $config): array
    {
        $title = $payload['title'] ?? 'Notification';
        $body = $payload['body'] ?? '';
        $actionUrl = $payload['action_url'] ?? null;

        $slackPayload = [
            'text' => $title,
            'attachments' => [
                [
                    'text' => $body,
                    'color' => '#3b82f6',
                ],
            ],
        ];

        if ($actionUrl) {
            $actionText = $payload['action_text'] ?? 'View Details';
            $slackPayload['attachments'][0]['actions'] = [
                [
                    'type' => 'button',
                    'text' => $actionText,
                    'url' => $actionUrl,
                ],
            ];
        }

        if (! empty($config['channel'])) {
            $slackPayload['channel'] = $config['channel'];
        }

        if (! empty($config['username'])) {
            $slackPayload['username'] = $config['username'];
        }

        if (! empty($config['icon_emoji'])) {
            $slackPayload['icon_emoji'] = $config['icon_emoji'];
        }

        return $slackPayload;
    }
}
