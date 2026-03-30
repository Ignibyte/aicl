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
class SmsDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        $config = $channel->config;
        $provider = $config['provider'] ?? 'twilio';

        return match ($provider) {
            'twilio' => $this->sendViaTwilio($config, $payload),
            default => DriverResult::failure(
                error: "Unsupported SMS provider: {$provider}",
                retryable: false,
            ),
        };
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['provider'])) {
            $errors['provider'] = 'SMS provider is required.';
        }

        $provider = $config['provider'] ?? 'twilio';

        if ($provider === 'twilio') {
            if (empty($config['account_sid'])) {
                $errors['account_sid'] = 'Twilio Account SID is required.';
            }
            if (empty($config['auth_token'])) {
                $errors['auth_token'] = 'Twilio Auth Token is required.';
            }
            if (empty($config['from'])) {
                $errors['from'] = 'From phone number is required.';
            }
            if (empty($config['to'])) {
                $errors['to'] = 'Recipient phone number is required.';
            }
        }

        return $errors;
    }

    /**
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function configSchema(): array
    {
        return [
            'provider' => ['type' => 'string', 'label' => 'SMS Provider', 'required' => true],
            'account_sid' => ['type' => 'string', 'label' => 'Account SID', 'required' => true],
            'auth_token' => ['type' => 'string', 'label' => 'Auth Token', 'required' => true],
            'from' => ['type' => 'string', 'label' => 'From Number', 'required' => true],
            'to' => ['type' => 'string', 'label' => 'To Number', 'required' => true],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     */
    protected function sendViaTwilio(array $config, array $payload): DriverResult
    {
        $accountSid = $config['account_sid'] ?? '';
        $authToken = $config['auth_token'] ?? '';
        $from = $config['from'] ?? '';
        $to = $config['to'] ?? '';

        $title = $payload['title'] ?? 'Notification';
        $body = $payload['body'] ?? '';
        $message = "{$title}: {$body}";

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        try {
            $response = Http::timeout(10)
                ->withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post($url, [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $responseData = $response->json();

                return DriverResult::success(
                    messageId: $responseData['sid'] ?? null,
                    response: $responseData,
                );
            }

            $retryable = $response->status() >= 500;

            return DriverResult::failure(
                error: "Twilio returned {$response->status()}: {$response->body()}",
                retryable: $retryable,
                response: ['status' => $response->status(), 'body' => $response->json() ?? []],
            );
        } catch (Throwable $e) {
            return DriverResult::failure(error: $e->getMessage());
        }
    }
}
