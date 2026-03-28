<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;

/**
 * Email HTML adapter.
 *
 * Wraps rendered template output in a simple inline-CSS HTML layout
 * with title, body paragraph, and optional action button.
 */
class EmailHtmlAdapter implements ChannelFormatAdapter
{
    public function format(array $rendered, array $context): array
    {
        $title = $rendered['title'];
        $body = $rendered['body'];
        $actionUrl = $rendered['action_url'] ?? null;
        $actionText = $rendered['action_text'] ?? 'View Details';

        $htmlBody = $this->buildHtml($title, $body, $actionUrl, $actionText);

        return [
            'title' => $title,
            'subject' => $title,
            'body' => $htmlBody,
            'action_url' => $actionUrl,
            'action_text' => $actionText,
        ];
    }

    public function channelType(): ChannelType
    {
        return ChannelType::Email;
    }

    /**
     * Build a simple inline-CSS HTML email body.
     */
    protected function buildHtml(
        string $title,
        string $body,
        ?string $actionUrl,
        string $actionText,
    ): string {
        $actionButton = '';
        if ($actionUrl) {
            $actionButton = <<<HTML
                <p style="margin: 24px 0;">
                    <a href="{$actionUrl}" style="display: inline-block; padding: 10px 20px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold;">{$actionText}</a>
                </p>
            HTML;
        }

        return <<<HTML
            <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #1f2937; margin-bottom: 16px;">{$title}</h2>
                <p style="color: #4b5563; line-height: 1.6;">{$body}</p>
                {$actionButton}
            </div>
        HTML;
    }
}
