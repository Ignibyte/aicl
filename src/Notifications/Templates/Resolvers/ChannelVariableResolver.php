<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;

/**
 * ChannelVariableResolver.
 */
class ChannelVariableResolver implements VariableResolver
{
    /** @var array<int, string> */
    protected array $denylist = ['config'];

    /**
     * Resolve a variable from $context['channel'] with denylist enforcement.
     */
    public function resolve(string $field, array $context): ?string
    {
        $channel = $context['channel'] ?? null;

        if (! is_object($channel)) {
            return null;
        }

        if (in_array($field, $this->denylist, true)) {
            return null;
        }

        $value = $channel->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }
}
