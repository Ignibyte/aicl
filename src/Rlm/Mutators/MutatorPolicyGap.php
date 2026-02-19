<?php

namespace Aicl\Rlm\Mutators;

/**
 * G.3: Removes the view() method from a policy.
 *
 * L1 pattern `policy.view_method` checks for `function view(`.
 * This mutator removes the entire view method.
 */
class MutatorPolicyGap implements Mutator
{
    public function name(): string
    {
        return 'policy_gap';
    }

    public function target(): string
    {
        return 'policy';
    }

    public function expectedFailures(): array
    {
        return ['policy.view_method'];
    }

    public function mutate(string $source): string
    {
        // Remove the view method block (public function view ... until next public function or closing brace)
        return preg_replace(
            '/\s*public function view\(.*?\{.*?\n\s*\}/s',
            '',
            $source,
        );
    }
}
