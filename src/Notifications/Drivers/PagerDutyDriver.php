<?php

namespace Aicl\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;
use Throwable;

class PagerDutyDriver implements NotificationChannelDriver
{
    private const EVENTS_API_URL = 'https://events.pagerduty.com/v2/enqueue';

    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        $config = $channel->config;
        $routingKey = $config['routing_key'] ?? '';

        $pagerDutyPayload = $this->buildPayload($payload, $config, $routingKey);

        try {
            $response = Http::timeout(10)->post(self::EVENTS_API_URL, $pagerDutyPayload);

            if ($response->successful()) {
                $body = $response->json();

                return DriverResult::success(
                    messageId: $body['dedup_key'] ?? null,
                    response: $body,
                );
            }

            $retryable = $response->status() >= 500 || $response->status() === 429;

            return DriverResult::failure(
                error: "PagerDuty returned {$response->status()}: {$response->body()}",
                retryable: $retryable,
                response: ['status' => $response->status(), 'body' => $response->json() ?? []],
            );
        } catch (Throwable $e) {
            return DriverResult::failure(error: $e->getMessage());
        }
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['routing_key'])) {
            $errors['routing_key'] = 'PagerDuty routing key is required.';
        }

        return $errors;
    }

    /**
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function configSchema(): array
    {
        return [
            'routing_key' => ['type' => 'string', 'label' => 'Routing Key', 'required' => true],
            'severity_map' => ['type' => 'array', 'label' => 'Severity Mapping', 'required' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function buildPayload(array $payload, array $config, string $routingKey): array
    {
        $title = $payload['title'] ?? 'Notification';
        $body = $payload['body'] ?? '';
        $severity = $this->mapSeverity($payload, $config);

        return [
            'routing_key' => $routingKey,
            'event_action' => 'trigger',
            'payload' => [
                'summary' => "{$title}: {$body}",
                'severity' => $severity,
                'source' => config('app.name', 'AICL'),
                'component' => $payload['entity_type'] ?? 'application',
                'custom_details' => $payload,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    protected function mapSeverity(array $payload, array $config): string
    {
        $severityMap = $config['severity_map'] ?? [];
        $color = $payload['color'] ?? 'info';

        if (! empty($severityMap[$color])) {
            return $severityMap[$color];
        }

        return match ($color) {
            'danger' => 'critical',
            'warning' => 'warning',
            'success' => 'info',
            default => 'info',
        };
    }
}
