<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Represents a single notification definition from a structured entity spec.
 */
class NotificationSpec
{
    /**
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public string $name,
        public string $trigger,
        public string $title,
        public string $body,
        public string $icon = 'heroicon-o-bell',
        public string $color = 'primary',
        public string $recipient = 'owner',
        public array $channels = ['database'],
    ) {}

    /**
     * Get the trigger type (field_change, state_transition, created, deleted).
     */
    public function triggerType(): string
    {
        if (str_starts_with($this->trigger, 'field_change:')) {
            return 'field_change';
        }

        if (str_starts_with($this->trigger, 'state_transition:')) {
            return 'state_transition';
        }

        return $this->trigger;
    }

    /**
     * Get the watched field name for field_change triggers.
     */
    public function watchedField(): ?string
    {
        if (! str_starts_with($this->trigger, 'field_change:')) {
            return null;
        }

        return trim(str_replace('field_change:', '', $this->trigger));
    }

    /**
     * Check if the color is dynamic (references a model field like "new.status.color").
     */
    public function hasDynamicColor(): bool
    {
        return str_contains($this->color, '.');
    }
}
