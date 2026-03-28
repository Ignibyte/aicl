<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;

/**
 * @codeCoverageIgnore Notification infrastructure
 */
class AppVariableResolver implements VariableResolver
{
    /** @var array<int, string> */
    protected array $allowlist = ['name', 'url', 'env', 'timezone'];

    /**
     * Resolve a variable from config('app.*') with allowlist enforcement.
     */
    public function resolve(string $field, array $context): ?string
    {
        if (! in_array($field, $this->allowlist, true)) {
            return null;
        }

        $value = config("app.{$field}");

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
