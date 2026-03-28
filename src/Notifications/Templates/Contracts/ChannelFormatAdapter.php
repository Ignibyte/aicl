<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Contracts;

use Aicl\Notifications\Enums\ChannelType;

/**
 * ChannelFormatAdapter.
 */
interface ChannelFormatAdapter
{
    /**
     * Format rendered template output into channel-native payload.
     *
     * @param  array{title: string, body: string, action_url: ?string, action_text: ?string, color: ?string}  $rendered
     * @param  array<string, mixed>  $context  The full rendering context (for advanced adapters)
     * @return array<string, mixed> Channel-native payload (passed to driver's send())
     */
    public function format(array $rendered, array $context): array;

    /**
     * Get the channel type this adapter handles.
     */
    public function channelType(): ChannelType;
}
