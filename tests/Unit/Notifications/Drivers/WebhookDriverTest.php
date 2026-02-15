<?php

namespace Aicl\Tests\Unit\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\Drivers\WebhookDriver;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookDriverTest extends TestCase
{
    use RefreshDatabase;

    private WebhookDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new WebhookDriver;
    }

    private function createChannel(array $config = []): NotificationChannel
    {
        return NotificationChannel::create([
            'name' => 'Webhook Test',
            'slug' => 'webhook-test-'.uniqid(),
            'type' => ChannelType::Webhook,
            'config' => array_merge(['url' => 'https://api.example.com/webhook'], $config),
            'is_active' => true,
        ]);
    }

    public function test_implements_notification_channel_driver(): void
    {
        $this->assertInstanceOf(NotificationChannelDriver::class, $this->driver);
    }

    public function test_send_post_successful(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['received' => true], 200, ['X-Request-Id' => 'req-123']),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
        $this->assertSame('req-123', $result->messageId);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'api.example.com/webhook');
        });
    }

    public function test_send_put_method(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = $this->createChannel(['method' => 'put']);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT';
        });
    }

    public function test_send_patch_method(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = $this->createChannel(['method' => 'patch']);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH';
        });
    }

    public function test_send_defaults_to_post_for_unknown_method(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = $this->createChannel(['method' => 'delete']);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST';
        });
    }

    public function test_send_with_custom_headers(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = $this->createChannel([
            'headers' => ['X-Custom-Header' => 'custom-value'],
        ]);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Custom-Header', 'custom-value');
        });
    }

    public function test_send_with_bearer_auth(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = $this->createChannel([
            'auth' => ['type' => 'bearer', 'token' => 'my-secret-token'],
        ]);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer my-secret-token');
        });
    }

    public function test_send_with_basic_auth(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = $this->createChannel([
            'auth' => ['type' => 'basic', 'username' => 'user', 'password' => 'pass'],
        ]);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $expected = 'Basic '.base64_encode('user:pass');

            return $request->hasHeader('Authorization', $expected);
        });
    }

    public function test_send_failure_with_client_error(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response('Bad Request', 400),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
    }

    public function test_send_failure_with_server_error_is_retryable(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response('Internal Server Error', 500),
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
            throw new \Exception('Connection refused');
        });

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertSame('Connection refused', $result->error);
    }

    public function test_send_response_includes_status_and_body(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['status' => 'accepted'], 200),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertArrayHasKey('status', $result->response);
        $this->assertSame(200, $result->response['status']);
    }

    public function test_validate_config_valid(): void
    {
        $errors = $this->driver->validateConfig([
            'url' => 'https://api.example.com/webhook',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_config_missing_url(): void
    {
        $errors = $this->driver->validateConfig([]);

        $this->assertArrayHasKey('url', $errors);
        $this->assertStringContainsString('required', $errors['url']);
    }

    public function test_validate_config_invalid_url(): void
    {
        $errors = $this->driver->validateConfig([
            'url' => 'not-a-url',
        ]);

        $this->assertArrayHasKey('url', $errors);
        $this->assertStringContainsString('valid URL', $errors['url']);
    }

    public function test_validate_config_invalid_method(): void
    {
        $errors = $this->driver->validateConfig([
            'url' => 'https://api.example.com/webhook',
            'method' => 'delete',
        ]);

        $this->assertArrayHasKey('method', $errors);
        $this->assertStringContainsString('post, put, patch', $errors['method']);
    }

    public function test_validate_config_valid_methods(): void
    {
        foreach (['post', 'put', 'patch'] as $method) {
            $errors = $this->driver->validateConfig([
                'url' => 'https://api.example.com/webhook',
                'method' => $method,
            ]);

            $this->assertEmpty($errors, "Method '{$method}' should be valid");
        }
    }

    public function test_validate_config_case_insensitive_method(): void
    {
        $errors = $this->driver->validateConfig([
            'url' => 'https://api.example.com/webhook',
            'method' => 'POST',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_config_schema_has_required_fields(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('url', $schema);
        $this->assertTrue($schema['url']['required']);
    }

    public function test_config_schema_has_optional_fields(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('method', $schema);
        $this->assertFalse($schema['method']['required']);

        $this->assertArrayHasKey('headers', $schema);
        $this->assertFalse($schema['headers']['required']);

        $this->assertArrayHasKey('auth', $schema);
        $this->assertFalse($schema['auth']['required']);
    }
}
