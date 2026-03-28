<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;

/**
 * Webhook JSON adapter.
 *
 * Passes the rendered payload through as-is, suitable for generic webhook consumers.
 */
class WebhookJsonAdapter implements ChannelFormatAdapter
{
    public function format(array $rendered, array $context): array
    {
        return $rendered;
    }

    public function channelType(): ChannelType
    {
        return ChannelType::Webhook;
    }
}
