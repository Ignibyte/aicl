<?php

namespace Aicl\Notifications;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\Enums\ChannelType;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class DriverRegistry
{
    /**
     * @var array<string, class-string<NotificationChannelDriver>>
     */
    private array $drivers = [];

    public function __construct(
        protected Container $container,
    ) {}

    /**
     * Register a driver class for a channel type.
     *
     * @param  class-string<NotificationChannelDriver>  $driverClass
     */
    public function register(ChannelType $type, string $driverClass): void
    {
        $this->drivers[$type->value] = $driverClass;
    }

    /**
     * Resolve a driver instance for a given channel type.
     */
    public function resolve(ChannelType $type): NotificationChannelDriver
    {
        if (! $this->has($type)) {
            throw new InvalidArgumentException("No driver registered for channel type: {$type->value}");
        }

        return $this->container->make($this->drivers[$type->value]);
    }

    /**
     * Get all registered driver types.
     *
     * @return array<string, class-string<NotificationChannelDriver>>
     */
    public function registered(): array
    {
        return $this->drivers;
    }

    /**
     * Check if a driver is registered for a type.
     */
    public function has(ChannelType $type): bool
    {
        return isset($this->drivers[$type->value]);
    }
}
