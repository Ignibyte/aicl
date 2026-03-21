<?php

namespace Aicl\Tests\Unit\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Drivers\TeamsDriver;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamsDriverTest extends TestCase
{
    use RefreshDatabase;

    private TeamsDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new TeamsDriver;
    }

    /** @phpstan-ignore-next-line */
    private function createChannel(array $config = []): NotificationChannel
    {
        return NotificationChannel::create([
            'name' => 'Teams Test',
            'slug' => 'teams-test-'.uniqid(),
            'type' => ChannelType::Teams,
            'config' => array_merge([
                'webhook_url' => 'https://outlook.office.com/webhook/test-hook',
            ], $config),
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
            'outlook.office.com/*' => Http::response('1', 200),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Deployment Complete', 'body' => 'Version 2.0 is live'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
        $this->assertInstanceOf(DriverResult::class, $result);
    }

    public function test_send_builds_adaptive_card_structure(): void
    {
        Http::fake([
            'outlook.office.com/*' => Http::response('1', 200),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test Title', 'body' => 'Test Body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $data = $request->data();

            // Check top-level structure
            if ($data['type'] !== 'message') {
                return false;
            }

            // Check attachment structure
            $attachment = $data['attachments'][0] ?? null;
            if (! $attachment) {
                return false;
            }

            if ($attachment['contentType'] !== 'application/vnd.microsoft.card.adaptive') {
                return false;
            }

            // Check card content
            $content = $attachment['content'];
            if ($content['type'] !== 'AdaptiveCard') {
                return false;
            }

            if ($content['version'] !== '1.4') {
                return false;
            }

            return true;
        });
    }

    public function test_send_includes_title_and_body_in_card(): void
    {
        Http::fake([
            'outlook.office.com/*' => Http::response('1', 200),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'My Title', 'body' => 'My Body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $content = $request['attachments'][0]['content'];
            $body = $content['body'];

            $titleBlock = $body[0] ?? null;
            $bodyBlock = $body[1] ?? null;

            return $titleBlock['type'] === 'TextBlock'
                && $titleBlock['text'] === 'My Title'
                && $titleBlock['weight'] === 'Bolder'
                && $bodyBlock['type'] === 'TextBlock'
                && $bodyBlock['text'] === 'My Body'
                && $bodyBlock['wrap'] === true;
        });
    }

    public function test_send_includes_action_when_url_provided(): void
    {
        Http::fake([
            'outlook.office.com/*' => Http::response('1', 200),
        ]);

        $channel = $this->createChannel();
        $payload = [
            'title' => 'Test',
            'body' => 'body',
            'action_url' => 'https://example.com/details',
            'action_text' => 'Open Details',
        ];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $content = $request['attachments'][0]['content'];
            $actions = $content['actions'];

            return count($actions) === 1
                && $actions[0]['type'] === 'Action.OpenUrl'
                && $actions[0]['title'] === 'Open Details'
                && $actions[0]['url'] === 'https://example.com/details';
        });
    }

    public function test_send_uses_default_action_text(): void
    {
        Http::fake([
            'outlook.office.com/*' => Http::response('1', 200),
        ]);

        $channel = $this->createChannel();
        $payload = [
            'title' => 'Test',
            'body' => 'body',
            'action_url' => 'https://example.com',
        ];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $actions = $request['attachments'][0]['content']['actions'];

            return $actions[0]['title'] === 'View Details';
        });
    }

    public function test_send_no_actions_when_no_url(): void
    {
        Http::fake([
            'outlook.office.com/*' => Http::response('1', 200),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $actions = $request['attachments'][0]['content']['actions'];

            return empty($actions);
        });
    }

    public function test_send_failure_with_client_error(): void
    {
        Http::fake([
            'outlook.office.com/*' => Http::response('Bad Request', 400),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('400', $result->error);
    }

    public function test_send_failure_with_server_error_is_retryable(): void
    {
        Http::fake([
            'outlook.office.com/*' => Http::response('Service Unavailable', 503),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    public function test_send_handles_connection_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Connection timed out');
        });

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertSame('Connection timed out', $result->error);
    }

    public function test_validate_config_valid(): void
    {
        $errors = $this->driver->validateConfig([
            'webhook_url' => 'https://outlook.office.com/webhook/test',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_config_missing_webhook_url(): void
    {
        $errors = $this->driver->validateConfig([]);

        $this->assertArrayHasKey('webhook_url', $errors);
        $this->assertStringContainsString('required', $errors['webhook_url']);
    }

    public function test_validate_config_invalid_webhook_url(): void
    {
        $errors = $this->driver->validateConfig([
            'webhook_url' => 'not-a-valid-url',
        ]);

        $this->assertArrayHasKey('webhook_url', $errors);
        $this->assertStringContainsString('valid URL', $errors['webhook_url']);
    }

    public function test_config_schema_has_required_webhook_url(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('webhook_url', $schema);
        $this->assertTrue($schema['webhook_url']['required']);
        $this->assertSame('url', $schema['webhook_url']['type']);
    }
}
