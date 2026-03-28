<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;
use InvalidArgumentException;

/**
 * FormatAdapterRegistry.
 */
class FormatAdapterRegistry
{
    /** @var array<string, ChannelFormatAdapter> */
    private array $adapters = [];

    /**
     * Register a format adapter for a channel type.
     */
    public function register(ChannelType $type, ChannelFormatAdapter $adapter): void
    {
        $this->adapters[$type->value] = $adapter;
    }

    /**
     * Resolve a format adapter for a channel type.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(ChannelType $type): ChannelFormatAdapter
    {
        if (! isset($this->adapters[$type->value])) {
            throw new InvalidArgumentException("Format adapter for channel type [{$type->value}] is not registered.");
        }

        return $this->adapters[$type->value];
    }

    /**
     * Check if an adapter is registered for a channel type.
     */
    public function has(ChannelType $type): bool
    {
        return isset($this->adapters[$type->value]);
    }

    /**
     * Get all registered adapters.
     *
     * @return array<string, ChannelFormatAdapter>
     */
    public function all(): array
    {
        return $this->adapters;
    }
}
