<?php

namespace Aicl\Rlm\Mutators;

/**
 * G.6: Injects a conflicting fix — replaces the correct extends with something plausible but wrong.
 *
 * Replaces `extends Model` with `extends BaseModel` — a plausible class name
 * that doesn't match the L1 pattern check for `extends Model`.
 * Tests accidental canonization: a "fix" that looks right locally but breaks validation.
 */
class MutatorConflictingFix implements Mutator
{
    public function name(): string
    {
        return 'conflicting_fix';
    }

    public function target(): string
    {
        return 'model';
    }

    public function expectedFailures(): array
    {
        return ['model.extends'];
    }

    public function mutate(string $source): string
    {
        return str_replace('extends Model', 'extends BaseModel', $source);
    }
}
