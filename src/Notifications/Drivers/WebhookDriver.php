<?php

namespace Aicl\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        $config = $channel->config;
        $url = $config['url'] ?? '';
        $method = strtolower($config['method'] ?? 'post');

        try {
            $request = $this->buildRequest($config);

            $response = match ($method) {
                'post' => $request->post($url, $payload),
                'put' => $request->put($url, $payload),
                'patch' => $request->patch($url, $payload),
                default => $request->post($url, $payload),
            };

            if ($response->successful()) {
                return DriverResult::success(
                    messageId: $response->header('X-Request-Id'),
                    response: ['status' => $response->status(), 'body' => $response->json() ?? []],
                );
            }

            $retryable = $response->status() >= 500;

            return DriverResult::failure(
                error: "Webhook returned {$response->status()}: {$response->body()}",
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

        if (empty($config['url'])) {
            $errors['url'] = 'Webhook URL is required.';
        } elseif (! filter_var($config['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = 'Webhook URL must be a valid URL.';
        }

        $validMethods = ['post', 'put', 'patch'];
        if (! empty($config['method']) && ! in_array(strtolower($config['method']), $validMethods, true)) {
            $errors['method'] = 'Method must be one of: post, put, patch.';
        }

        return $errors;
    }

    /**
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function configSchema(): array
    {
        return [
            'url' => ['type' => 'url', 'label' => 'Webhook URL', 'required' => true],
            'method' => ['type' => 'string', 'label' => 'HTTP Method', 'required' => false],
            'headers' => ['type' => 'array', 'label' => 'Custom Headers', 'required' => false],
            'auth' => ['type' => 'array', 'label' => 'Authentication', 'required' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function buildRequest(array $config): PendingRequest
    {
        $request = Http::timeout(10)->acceptJson();

        if (! empty($config['headers']) && is_array($config['headers'])) {
            $request = $request->withHeaders($config['headers']);
        }

        if (! empty($config['auth']) && is_array($config['auth'])) {
            $auth = $config['auth'];

            if (($auth['type'] ?? '') === 'bearer' && ! empty($auth['token'])) {
                $request = $request->withToken($auth['token']);
            } elseif (($auth['type'] ?? '') === 'basic' && ! empty($auth['username'])) {
                $request = $request->withBasicAuth($auth['username'], $auth['password'] ?? '');
            }
        }

        return $request;
    }
}
