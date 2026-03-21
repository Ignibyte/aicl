<?php

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Notifications\ChannelRateLimiter;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Tests\TestCase;

class ChannelRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    private ChannelRateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->limiter = new ChannelRateLimiter;
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('notification_channel:*');
        parent::tearDown();
    }

    /** @phpstan-ignore-next-line */
    private function createChannel(?array $rateLimit = null): NotificationChannel
    {
        return NotificationChannel::create([
            'name' => 'Test Channel',
            'slug' => 'test-channel-'.uniqid(),
            'type' => ChannelType::Slack,
            'config' => ['webhook_url' => 'https://hooks.slack.com/test'],
            'rate_limit' => $rateLimit,
            'is_active' => true,
        ]);
    }

    public function test_attempt_returns_true_when_no_rate_limit_configured(): void
    {
        $channel = $this->createChannel(null);

        $this->assertTrue($this->limiter->attempt($channel));
    }

    public function test_attempt_returns_true_when_rate_limit_missing_max(): void
    {
        $channel = $this->createChannel(['period' => '1m']);

        $this->assertTrue($this->limiter->attempt($channel));
    }

    public function test_attempt_returns_true_when_rate_limit_missing_period(): void
    {
        $channel = $this->createChannel(['max' => 10]);

        $this->assertTrue($this->limiter->attempt($channel));
    }

    public function test_attempt_returns_true_under_limit(): void
    {
        $channel = $this->createChannel(['max' => 5, 'period' => '1m']);

        $this->assertTrue($this->limiter->attempt($channel));
    }

    public function test_attempt_returns_false_when_limit_exceeded(): void
    {
        $channel = $this->createChannel(['max' => 2, 'period' => '1m']);

        // Use up the limit
        $this->assertTrue($this->limiter->attempt($channel));
        $this->assertTrue($this->limiter->attempt($channel));

        // This should be rate-limited
        $this->assertFalse($this->limiter->attempt($channel));
    }

    public function test_parse_period_seconds(): void
    {
        $channel = $this->createChannel(['max' => 100, 'period' => '30s']);

        // If parsing works, the attempt should succeed
        $this->assertTrue($this->limiter->attempt($channel));
    }

    public function test_parse_period_minutes(): void
    {
        $channel = $this->createChannel(['max' => 100, 'period' => '5m']);

        $this->assertTrue($this->limiter->attempt($channel));
    }

    public function test_parse_period_hours(): void
    {
        $channel = $this->createChannel(['max' => 100, 'period' => '1h']);

        $this->assertTrue($this->limiter->attempt($channel));
    }

    public function test_parse_period_invalid_format_throws(): void
    {
        $channel = $this->createChannel(['max' => 10, 'period' => 'invalid']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid rate limit period format: invalid');

        $this->limiter->attempt($channel);
    }

    public function test_parse_period_numeric_without_unit_throws(): void
    {
        $channel = $this->createChannel(['max' => 10, 'period' => '60']);

        $this->expectException(InvalidArgumentException::class);

        $this->limiter->attempt($channel);
    }

    public function test_available_in_returns_zero_when_not_limited(): void
    {
        $channel = $this->createChannel(['max' => 100, 'period' => '1m']);

        // Haven't made any attempts, so available_in should be 0
        $this->assertSame(0, $this->limiter->availableIn($channel));
    }

    public function test_rate_limit_uses_channel_id_as_key(): void
    {
        $channel1 = $this->createChannel(['max' => 1, 'period' => '1m']);
        $channel2 = $this->createChannel(['max' => 1, 'period' => '1m']);

        // Exhaust channel1's limit
        $this->assertTrue($this->limiter->attempt($channel1));
        $this->assertFalse($this->limiter->attempt($channel1));

        // Channel2 should still work — independent rate limit
        $this->assertTrue($this->limiter->attempt($channel2));
    }
}
