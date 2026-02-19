<?php

namespace Aicl\Rlm\Mutators;

/**
 * G.4: Removes the $model property from a factory.
 *
 * L1 pattern `factory.model_property` checks for `protected $model`.
 * This mutator removes the explicit model binding line.
 */
class MutatorFactoryTypeMismatch implements Mutator
{
    public function name(): string
    {
        return 'factory_type_mismatch';
    }

    public function target(): string
    {
        return 'factory';
    }

    public function expectedFailures(): array
    {
        return ['factory.model_property'];
    }

    public function mutate(string $source): string
    {
        return preg_replace('/^\s*protected \$model = .*?;\s*$/m', '', $source);
    }
}
