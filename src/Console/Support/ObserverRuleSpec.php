<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Represents a single observer rule from a structured entity spec.
 *
 * Rules define what actions to take on model lifecycle events.
 * Actions: "log" (activity log) or "notify" (dispatch notification).
 */
class ObserverRuleSpec
{
    /**
     * @param  string  $event  Lifecycle event: 'created', 'updated', 'deleted'
     * @param  string  $action  Action type: 'log' or 'notify'
     * @param  string  $details  Action details (log message template or notification recipient:class)
     * @param  string|null  $watchField  For 'updated' event: field to watch via isDirty() (null for created/deleted)
     */
    public function __construct(
        public string $event,
        public string $action,
        public string $details,
        public ?string $watchField = null,
    ) {}

    /**
     * Whether this is a logging action.
     */
    public function isLog(): bool
    {
        return $this->action === 'log';
    }

    /**
     * Whether this is a notification action.
     */
    public function isNotify(): bool
    {
        return $this->action === 'notify';
    }

    /**
     * Parse the notify details to extract recipient and notification class.
     *
     * Format: "recipient: NotificationClassName" or "recipient: ClassName (if condition)"
     *
     * @return array{recipient: string, class: string, condition: string|null}
     */
    public function parseNotifyDetails(): array
    {
        $details = trim($this->details);
        $condition = null;

        // Extract condition: "(if owner_id set)" or "(if condition)"
        if (preg_match('/\(if\s+(.+?)\)\s*$/', $details, $m)) {
            $condition = trim($m[1]);
            $details = trim(preg_replace('/\(if\s+.+?\)\s*$/', '', $details) ?? '');
        }

        // Split "recipient: ClassName"
        $parts = array_map('trim', explode(':', $details, 2));
        $recipient = $parts[0];
        $class = $parts[1] ?? '';

        // Handle "new owner" → "owner" with "new" prefix for context
        $recipient = preg_replace('/^new\s+/', '', $recipient) ?? $recipient;

        return [
            'recipient' => $recipient,
            'class' => $class,
            'condition' => $condition,
        ];
    }
}
