<?php

namespace Aicl\Tests\Unit\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Drivers\EmailDriver;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailDriverTest extends TestCase
{
    use RefreshDatabase;

    private EmailDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new EmailDriver;
    }

    private function createChannel(array $config = []): NotificationChannel
    {
        return NotificationChannel::create([
            'name' => 'Email Test',
            'slug' => 'email-test-'.uniqid(),
            'type' => ChannelType::Email,
            'config' => array_merge(['to' => ['admin@example.com']], $config),
            'is_active' => true,
        ]);
    }

    public function test_implements_notification_channel_driver(): void
    {
        $this->assertInstanceOf(NotificationChannelDriver::class, $this->driver);
    }

    public function test_send_successful(): void
    {
        Mail::fake();

        $channel = $this->createChannel();
        $payload = ['title' => 'Test Email', 'body' => 'Hello World'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
        $this->assertInstanceOf(DriverResult::class, $result);
        $this->assertSame(['admin@example.com'], $result->response['recipients']);
    }

    public function test_send_with_subject_prefix(): void
    {
        Mail::fake();

        $channel = $this->createChannel(['subject_prefix' => '[AICL]']);
        $payload = ['title' => 'Alert', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
    }

    public function test_send_without_subject_prefix(): void
    {
        Mail::fake();

        $channel = $this->createChannel();
        $payload = ['title' => 'Direct Subject', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
    }

    public function test_send_with_custom_from(): void
    {
        Mail::fake();

        $channel = $this->createChannel(['from' => 'noreply@example.com']);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
    }

    public function test_send_with_multiple_recipients(): void
    {
        Mail::fake();

        $channel = $this->createChannel(['to' => ['one@example.com', 'two@example.com']]);
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertTrue($result->success);
        $this->assertSame(['one@example.com', 'two@example.com'], $result->response['recipients']);
    }

    public function test_send_response_includes_recipients(): void
    {
        Mail::fake();

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertArrayHasKey('recipients', $result->response);
    }

    public function test_send_handles_mail_exception(): void
    {
        Mail::shouldReceive('raw')->andThrow(new \Exception('SMTP connection failed'));

        $channel = $this->createChannel();
        $payload = ['title' => 'Test', 'body' => 'body'];

        $result = $this->driver->send($channel, $payload);

        $this->assertFalse($result->success);
        $this->assertSame('SMTP connection failed', $result->error);
    }

    public function test_validate_config_valid(): void
    {
        $errors = $this->driver->validateConfig([
            'to' => ['admin@example.com'],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_config_missing_to(): void
    {
        $errors = $this->driver->validateConfig([]);

        $this->assertArrayHasKey('to', $errors);
        $this->assertStringContainsString('required', $errors['to']);
    }

    public function test_validate_config_invalid_email_in_to(): void
    {
        $errors = $this->driver->validateConfig([
            'to' => ['not-an-email'],
        ]);

        $this->assertArrayHasKey('to', $errors);
        $this->assertStringContainsString('Invalid email', $errors['to']);
    }

    public function test_validate_config_invalid_from(): void
    {
        $errors = $this->driver->validateConfig([
            'to' => ['admin@example.com'],
            'from' => 'not-an-email',
        ]);

        $this->assertArrayHasKey('from', $errors);
        $this->assertStringContainsString('valid email', $errors['from']);
    }

    public function test_validate_config_valid_from(): void
    {
        $errors = $this->driver->validateConfig([
            'to' => ['admin@example.com'],
            'from' => 'noreply@example.com',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_config_schema_has_required_fields(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('to', $schema);
        $this->assertTrue($schema['to']['required']);
        $this->assertSame('array', $schema['to']['type']);
    }

    public function test_config_schema_has_optional_fields(): void
    {
        $schema = $this->driver->configSchema();

        $this->assertArrayHasKey('from', $schema);
        $this->assertFalse($schema['from']['required']);

        $this->assertArrayHasKey('subject_prefix', $schema);
        $this->assertFalse($schema['subject_prefix']['required']);
    }
}
