<?php

namespace Aicl\Rlm\Mutators;

/**
 * G.2: Removes the HasFactory trait from a model.
 *
 * L1 pattern `model.has_factory` checks for `use HasFactory;`.
 * This mutator removes that line entirely.
 */
class MutatorMissingTrait implements Mutator
{
    public function name(): string
    {
        return 'missing_trait';
    }

    public function target(): string
    {
        return 'model';
    }

    public function expectedFailures(): array
    {
        return ['model.has_factory'];
    }

    public function mutate(string $source): string
    {
        return preg_replace('/^\s*use HasFactory;\s*$/m', '', $source);
    }
}
