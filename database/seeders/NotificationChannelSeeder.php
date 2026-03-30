<?php

declare(strict_types=1);

namespace Aicl\Database\Seeders;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Database\Seeder;

class NotificationChannelSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate and re-insert instead of updateOrCreate to avoid
        // decryption failures when APP_KEY changes between runs
        // (the 'config' column uses encrypted:array casting).
        NotificationChannel::query()->truncate();

        foreach ($this->channels() as $channel) {
            NotificationChannel::query()->create($channel);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function channels(): array
    {
        return [
            [
                'name' => 'System Email',
                'slug' => 'system-email',
                'type' => ChannelType::Email,
                'config' => [
                    'from_address' => config('mail.from.address', 'noreply@example.com'),
                    'from_name' => config('mail.from.name', config('app.name')),
                ],
                'message_templates' => [
                    '_default' => [
                        'title' => '{{ title }}',
                        'body' => '{{ body }}',
                    ],
                ],
                'rate_limit' => ['max' => 60, 'period' => '1m'],
                'is_active' => true,
            ],
            [
                'name' => 'Slack Alerts',
                'slug' => 'slack-alerts',
                'type' => ChannelType::Slack,
                'config' => [
                    'webhook_url' => env('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/services/PLACEHOLDER'),
                ],
                'message_templates' => [
                    '_default' => [
                        'title' => '{{ title }}',
                        'body' => '{{ body }}',
                    ],
                ],
                'rate_limit' => ['max' => 30, 'period' => '1m'],
                'is_active' => false,
            ],
            [
                'name' => 'Generic Webhook',
                'slug' => 'generic-webhook',
                'type' => ChannelType::Webhook,
                'config' => [
                    'url' => env('WEBHOOK_URL', 'https://example.com/webhook'),
                    'method' => 'POST',
                    'headers' => ['Content-Type' => 'application/json'],
                ],
                'message_templates' => [
                    '_default' => [
                        'title' => '{{ title }}',
                        'body' => '{{ body }}',
                    ],
                ],
                'rate_limit' => ['max' => 100, 'period' => '1m'],
                'is_active' => false,
            ],
        ];
    }
}
