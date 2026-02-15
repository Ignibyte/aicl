<?php

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\DriverRegistry;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use InvalidArgumentException;
use Tests\TestCase;

class DriverRegistryTest extends TestCase
{
    private DriverRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new DriverRegistry($this->app);
    }

    public function test_register_and_has(): void
    {
        $this->assertFalse($this->registry->has(ChannelType::Slack));

        $this->registry->register(ChannelType::Slack, TestableDriver::class);

        $this->assertTrue($this->registry->has(ChannelType::Slack));
    }

    public function test_resolve_returns_driver_instance(): void
    {
        $this->registry->register(ChannelType::Slack, TestableDriver::class);

        $driver = $this->registry->resolve(ChannelType::Slack);

        $this->assertInstanceOf(NotificationChannelDriver::class, $driver);
        $this->assertInstanceOf(TestableDriver::class, $driver);
    }

    public function test_resolve_throws_for_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No driver registered for channel type: slack');

        $this->registry->resolve(ChannelType::Slack);
    }

    public function test_registered_returns_all_registered_drivers(): void
    {
        $this->assertEmpty($this->registry->registered());

        $this->registry->register(ChannelType::Slack, TestableDriver::class);
        $this->registry->register(ChannelType::Email, TestableDriver::class);

        $registered = $this->registry->registered();

        $this->assertCount(2, $registered);
        $this->assertArrayHasKey('slack', $registered);
        $this->assertArrayHasKey('email', $registered);
    }

    public function test_register_overwrites_existing_driver(): void
    {
        $this->registry->register(ChannelType::Slack, TestableDriver::class);
        $this->registry->register(ChannelType::Slack, AnotherTestableDriver::class);

        $driver = $this->registry->resolve(ChannelType::Slack);

        $this->assertInstanceOf(AnotherTestableDriver::class, $driver);
    }

    public function test_has_returns_false_for_unregistered_type(): void
    {
        $this->assertFalse($this->registry->has(ChannelType::PagerDuty));
    }

    public function test_registering_multiple_types(): void
    {
        foreach (ChannelType::cases() as $type) {
            $this->registry->register($type, TestableDriver::class);
        }

        $this->assertCount(6, $this->registry->registered());

        foreach (ChannelType::cases() as $type) {
            $this->assertTrue($this->registry->has($type));
        }
    }
}

class TestableDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        return DriverResult::success();
    }

    public function validateConfig(array $config): array
    {
        return [];
    }

    public function configSchema(): array
    {
        return [];
    }
}

class AnotherTestableDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        return DriverResult::success();
    }

    public function validateConfig(array $config): array
    {
        return [];
    }

    public function configSchema(): array
    {
        return [];
    }
}
