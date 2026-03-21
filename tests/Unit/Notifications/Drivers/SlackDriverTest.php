<?php

namespace Aicl\Tests\Unit\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Drivers\SlackDriver;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackDriverTest extends TestCase
{
    use RefreshDatabase;

    private SlackDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new SlackDriver;
    }

    /** @phpstan-ignore-next-line */
    private function createChannel(array $config = []): NotificationChannel
    {
        return NotificationChannel::create([
            'name' => 'Slack Test',
            'slug' => 'slack-test-'.uniqid(),
            'type' => ChannelType::Slack,
            'config' => array_merge(['webhook_url' => 'https://hooks.slack.com/services/T00/B00/XXXX'], $config),
            'is_active' => true,
        ]);
    }

    public function test_implements_notification_channel_driver(): void
    {
        $this->assertInstanceOf(NotificationChannelDriver::class, $this->driver);
    }

    public function test_send_successful(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test Alert', 'body' => 'Something happened'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
        $this->assertInstanceOf(DriverResult::class, $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com')
                && $request['text'] === 'Test Alert';
        });
    }

    public function test_send_includes_attachments_with_body(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Alert', 'body' => 'Details here'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $attachments = $request['attachments'] ?? [];

            return count($attachments) === 1
                && $attachments[0]['text'] === 'Details here';
        });
    }

    public function test_send_includes_action_button_when_url_provided(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        $channel = $this->createChannel();
        $payload = [
            'title' => 'Alert',
            'body' => 'Details',
            'action_url' => 'https://example.com/view',
            'action_text' => 'View It',
        ];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $actions = $request['attachments'][0]['actions'] ?? [];

            return count($actions) === 1
                && $actions[0]['text'] === 'View It'
                && $actions[0]['url'] === 'https://example.com/view';
        });
    }

    public function test_send_uses_channel_override(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        $channel = $this->createChannel(['channel' => '#alerts']);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['channel'] === '#alerts';
        });
    }

    public function test_send_uses_custom_username(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        $channel = $this->createChannel(['username' => 'AICL Bot']);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['username'] === 'AICL Bot';
        });
    }

    public function test_send_uses_icon_emoji(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        $channel = $this->createChannel(['icon_emoji' => ':robot_face:']);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['icon_emoji'] === ':robot_face:';
        });
    }

    public function test_send_failure_with_client_error(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('invalid_payload', 400),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        /** @phpstan-ignore-next-line */
        $this->assertStringContains('400', $result->error);
    }

    public function test_send_failure_with_server_error_is_retryable(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    public function test_send_handles_http_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Connection timeout');
        });

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertSame('Connection timeout', $result->error);
    }

    public function test_validate_config_valid(): void
    {
        $errors = $this->driver->validateConfig([
            'webhook_url' => 'https://hooks.slack.com/services/T00/B00/XXXX',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_config_missing_webhook_url(): void
    {
        $errors = $this->driver->validateConfig([]);

        $this->assertArrayHasKey('webhook_url', $errors);
        $this->assertStringContains('required', $errors['webhook_url']);
    }

    public function test_validate_config_invalid_webhook_url(): void
    {
        $errors = $this->driver->validateConfig([
            'webhook_url' => 'not-a-url',
        ]);

        $this->assertArrayHasKey('webhook_url', $errors);
        $this->assertStringContains('valid URL', $errors['webhook_url']);
    }

    public function test_config_schema_has_required_fields(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('webhook_url', $schema);
        $this->assertTrue($schema['webhook_url']['required']);
        $this->assertSame('url', $schema['webhook_url']['type']);
    }

    public function test_config_schema_has_optional_fields(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('channel', $schema);
        $this->assertFalse($schema['channel']['required']);

        $this->assertArrayHasKey('username', $schema);
        $this->assertFalse($schema['username']['required']);

        $this->assertArrayHasKey('icon_emoji', $schema);
        $this->assertFalse($schema['icon_emoji']['required']);
    }

    /**
     * Helper assertion for string containment.
     */
    private static function assertStringContains(string $needle, string $haystack): void
    {
        self::assertStringContainsString($needle, $haystack);
    }
}
