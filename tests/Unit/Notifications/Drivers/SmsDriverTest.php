<?php

namespace Aicl\Tests\Unit\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\Drivers\SmsDriver;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsDriverTest extends TestCase
{
    use RefreshDatabase;

    private SmsDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new SmsDriver;
    }

    private function createChannel(array $config = []): NotificationChannel
    {
        return NotificationChannel::create([
            'name' => 'SMS Test',
            'slug' => 'sms-test-'.uniqid(),
            'type' => ChannelType::Sms,
            'config' => array_merge([
                'provider' => 'twilio',
                'account_sid' => 'ACtest123',
                'auth_token' => 'auth-token-secret',
                'from' => '+15551234567',
                'to' => '+15559876543',
            ], $config),
            'is_active' => true,
        ]);
    }

    public function test_implements_notification_channel_driver(): void
    {
        $this->assertInstanceOf(NotificationChannelDriver::class, $this->driver);
    }

    public function test_send_via_twilio_successful(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response([
                'sid' => 'SM12345',
                'status' => 'queued',
            ], 201),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Alert', 'body' => 'Server is down'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
        $this->assertSame('SM12345', $result->messageId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.twilio.com')
                && str_contains($request->url(), 'ACtest123')
                && str_contains($request->url(), 'Messages.json');
        });
    }

    public function test_send_via_twilio_uses_basic_auth(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $expected = 'Basic '.base64_encode('ACtest123:auth-token-secret');

            return $request->hasHeader('Authorization', $expected);
        });
    }

    public function test_send_via_twilio_sends_form_data(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Alert', 'body' => 'Details'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'To=%2B15559876543')
                && str_contains($body, 'From=%2B15551234567')
                && str_contains($body, 'Body=Alert');
        });
    }

    public function test_send_message_combines_title_and_body(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Alert', 'body' => 'Server down'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            // The body should contain "Alert: Server down"
            return str_contains($request->body(), urlencode('Alert: Server down'));
        });
    }

    public function test_send_unsupported_provider(): void
    {
        $channel = $this->createChannel(['provider' => 'vonage']);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        $this->assertStringContainsString('Unsupported SMS provider', $result->error);
    }

    public function test_send_failure_with_client_error(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response([
                'code' => 21211,
                'message' => 'Invalid phone number',
            ], 400),
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
            'api.twilio.com/*' => Http::response('Internal Server Error', 500),
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
            throw new \Exception('Network unreachable');
        });

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertSame('Network unreachable', $result->error);
    }

    public function test_validate_config_valid(): void
    {
        $errors = $this->driver->validateConfig([
            'provider' => 'twilio',
            'account_sid' => 'ACtest123',
            'auth_token' => 'token',
            'from' => '+15551234567',
            'to' => '+15559876543',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_config_missing_provider(): void
    {
        $errors = $this->driver->validateConfig([]);

        $this->assertArrayHasKey('provider', $errors);
        $this->assertStringContainsString('required', $errors['provider']);
    }

    public function test_validate_config_missing_twilio_fields(): void
    {
        $errors = $this->driver->validateConfig([
            'provider' => 'twilio',
        ]);

        $this->assertArrayHasKey('account_sid', $errors);
        $this->assertArrayHasKey('auth_token', $errors);
        $this->assertArrayHasKey('from', $errors);
        $this->assertArrayHasKey('to', $errors);
    }

    public function test_validate_config_partial_twilio_fields(): void
    {
        $errors = $this->driver->validateConfig([
            'provider' => 'twilio',
            'account_sid' => 'ACtest123',
        ]);

        $this->assertArrayNotHasKey('account_sid', $errors);
        $this->assertArrayHasKey('auth_token', $errors);
        $this->assertArrayHasKey('from', $errors);
        $this->assertArrayHasKey('to', $errors);
    }

    public function test_config_schema_has_required_fields(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('provider', $schema);
        $this->assertTrue($schema['provider']['required']);

        $this->assertArrayHasKey('account_sid', $schema);
        $this->assertTrue($schema['account_sid']['required']);

        $this->assertArrayHasKey('auth_token', $schema);
        $this->assertTrue($schema['auth_token']['required']);

        $this->assertArrayHasKey('from', $schema);
        $this->assertTrue($schema['from']['required']);

        $this->assertArrayHasKey('to', $schema);
        $this->assertTrue($schema['to']['required']);
    }

    public function test_config_schema_has_correct_labels(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertSame('SMS Provider', $schema['provider']['label']);
        $this->assertSame('Account SID', $schema['account_sid']['label']);
        $this->assertSame('Auth Token', $schema['auth_token']['label']);
        $this->assertSame('From Number', $schema['from']['label']);
        $this->assertSame('To Number', $schema['to']['label']);
    }
}
