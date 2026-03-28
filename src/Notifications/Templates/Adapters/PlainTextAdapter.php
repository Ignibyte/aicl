<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;

/**
 * Plain text adapter for SMS channels.
 *
 * Strips HTML tags and returns title + body as plain text.
 */
class PlainTextAdapter implements ChannelFormatAdapter
{
    public function format(array $rendered, array $context): array
    {
        return [
            'title' => strip_tags($rendered['title']),
            'body' => strip_tags($rendered['body']),
        ];
    }

    public function channelType(): ChannelType
    {
        return ChannelType::Sms;
    }
}
