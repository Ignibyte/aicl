<?php

declare(strict_types=1);

namespace Aicl\Notifications\Contracts;

use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Models\NotificationChannel;

/**
 * NotificationChannelDriver.
 */
interface NotificationChannelDriver
{
    /**
     * Send a notification payload through this channel.
     *
     * @param  NotificationChannel  $channel  The channel config (with decrypted credentials)
     * @param  array<string, mixed>  $payload  The notification data (title, body, action_url, etc.)
     */
    public function send(NotificationChannel $channel, array $payload): DriverResult;

    /**
     * Validate that the channel config contains all required fields for this driver.
     *
     * @param  array<string, mixed>  $config  The decrypted config array
     * @return array<string, string> Empty if valid, field => error message if invalid
     */
    public function validateConfig(array $config): array;

    /**
     * Get the required config fields for this driver type.
     *
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function configSchema(): array;
}
