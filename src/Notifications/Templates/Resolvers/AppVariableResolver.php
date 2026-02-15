<?php

namespace Aicl\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;

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
