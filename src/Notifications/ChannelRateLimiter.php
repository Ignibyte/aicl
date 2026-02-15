<?php

namespace Aicl\Notifications;

use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

class ChannelRateLimiter
{
    /**
     * Check if a channel can accept a delivery right now.
     * Returns true if under limit, false if rate-limited.
     */
    public function attempt(NotificationChannel $channel): bool
    {
        $config = $channel->rate_limit;

        if (! $config || ! isset($config['max'], $config['period'])) { // @phpstan-ignore isset.offset, isset.offset
            return true;
        }

        $key = "notification_channel:{$channel->id}";
        $maxAttempts = (int) $config['max'];
        $decaySeconds = $this->parsePeriod($config['period']);

        return RateLimiter::attempt($key, $maxAttempts, fn () => true, $decaySeconds);
    }

    /**
     * Get seconds until the rate limit resets for a channel.
     */
    public function availableIn(NotificationChannel $channel): int
    {
        $key = "notification_channel:{$channel->id}";

        return RateLimiter::availableIn($key);
    }

    /**
     * Parse period string to seconds. Supports: "30s", "1m", "5m", "1h".
     */
    protected function parsePeriod(string $period): int
    {
        if (preg_match('/^(\d+)(s|m|h)$/', $period, $matches)) {
            $value = (int) $matches[1];

            return match ($matches[2]) {
                's' => $value,
                'm' => $value * 60,
                'h' => $value * 3600,
            };
        }

        throw new InvalidArgumentException("Invalid rate limit period format: {$period}. Use format like '30s', '1m', '1h'.");
    }
}
