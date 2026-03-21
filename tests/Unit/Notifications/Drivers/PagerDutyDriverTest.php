<?php

namespace Aicl\Tests\Unit\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\Drivers\PagerDutyDriver;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PagerDutyDriverTest extends TestCase
{
    use RefreshDatabase;

    private PagerDutyDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new PagerDutyDriver;
    }

    /** @phpstan-ignore-next-line */
    private function createChannel(array $config = []): NotificationChannel
    {
        return NotificationChannel::create([
            'name' => 'PagerDuty Test',
            'slug' => 'pagerduty-test-'.uniqid(),
            'type' => ChannelType::PagerDuty,
            'config' => array_merge(['routing_key' => 'test-routing-key-123'], $config),
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
            'events.pagerduty.com/*' => Http::response([
                'status' => 'success',
                'dedup_key' => 'dedup-abc-123',
                'message' => 'Event processed',
            ], 202),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Server Down', 'body' => 'Production server is unreachable'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
        $this->assertSame('dedup-abc-123', $result->messageId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'events.pagerduty.com/v2/enqueue')
                && $request['routing_key'] === 'test-routing-key-123'
                && $request['event_action'] === 'trigger';
        });
    }

    public function test_send_includes_payload_summary(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response(['status' => 'success', 'dedup_key' => 'x'], 202),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Alert', 'body' => 'Details here'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            $pd = $request->data();

            return $pd['payload']['summary'] === 'Alert: Details here';
        });
    }

    public function test_send_maps_danger_to_critical_severity(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response(['status' => 'success', 'dedup_key' => 'x'], 202),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Critical', 'body' => 'body', 'color' => 'danger'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['payload']['severity'] === 'critical';
        });
    }

    public function test_send_maps_warning_to_warning_severity(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response(['status' => 'success', 'dedup_key' => 'x'], 202),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Warning', 'body' => 'body', 'color' => 'warning'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['payload']['severity'] === 'warning';
        });
    }

    public function test_send_maps_success_to_info_severity(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response(['status' => 'success', 'dedup_key' => 'x'], 202),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Good', 'body' => 'body', 'color' => 'success'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['payload']['severity'] === 'info';
        });
    }

    public function test_send_default_severity_is_info(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response(['status' => 'success', 'dedup_key' => 'x'], 202),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Default', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['payload']['severity'] === 'info';
        });
    }

    public function test_send_uses_custom_severity_map(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response(['status' => 'success', 'dedup_key' => 'x'], 202),
        ]);

        $channel = $this->createChannel([
            'severity_map' => ['danger' => 'error', 'warning' => 'info'],
        ]);
        $payload = ['title' => 'Custom', 'body' => 'body', 'color' => 'danger'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['payload']['severity'] === 'error';
        });
    }

    public function test_send_includes_entity_type_as_component(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response(['status' => 'success', 'dedup_key' => 'x'], 202),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body', 'entity_type' => 'server'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['payload']['component'] === 'server';
        });
    }

    public function test_send_defaults_component_to_application(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response(['status' => 'success', 'dedup_key' => 'x'], 202),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $this->driver->send($channel, $payload);

        Http::assertSent(function ($request) {
            return $request['payload']['component'] === 'application';
        });
    }

    public function test_send_failure_with_client_error(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response(['status' => 'invalid event'], 400),
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
            'events.pagerduty.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    public function test_send_failure_with_429_is_retryable(): void
    {
        Http::fake([
            'events.pagerduty.com/*' => Http::response('Rate limited', 429),
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
            throw new \Exception('DNS resolution failed');
        });

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertSame('DNS resolution failed', $result->error);
    }

    public function test_validate_config_valid(): void
    {
        $errors = $this->driver->validateConfig([
            'routing_key' => 'test-key-123',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_config_missing_routing_key(): void
    {
        $errors = $this->driver->validateConfig([]);

        $this->assertArrayHasKey('routing_key', $errors);
        $this->assertStringContainsString('required', $errors['routing_key']);
    }

    public function test_config_schema_has_required_fields(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('routing_key', $schema);
        $this->assertTrue($schema['routing_key']['required']);
    }

    public function test_config_schema_has_optional_severity_map(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('severity_map', $schema);
        $this->assertFalse($schema['severity_map']['required']);
    }
}
