<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates;

/**
 * Static example templates for all built-in AICL notification types.
 *
 * Useful for seeders, testing, and documentation. These templates are NOT
 * automatically applied -- they are provided for reference and opt-in use.
 */
class TemplateExamples
{
    /**
     * Get example templates for all built-in AICL notification types.
     *
     * @return array<string, array{title: string, body: string}>
     */
    public static function all(): array
    {
        return [
            '_default' => [
                'title' => '{{ title }}',
                'body' => '{{ body }}',
            ],
        ];
    }

    /**
     * Get example templates for a single notification type.
     *
     * @return array{title: string, body: string}|null
     */
    public static function for(string $notificationClass): ?array
    {
        return static::all()[$notificationClass] ?? null;
    }
}
