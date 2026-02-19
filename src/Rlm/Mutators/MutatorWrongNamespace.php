<?php

namespace Aicl\Rlm\Mutators;

/**
 * G.1: Changes model namespace to a wrong namespace.
 *
 * L1 pattern `model.namespace` checks for `namespace (App|Aicl)\Models;`.
 * This mutator changes it to a wrong namespace that won't match.
 */
class MutatorWrongNamespace implements Mutator
{
    public function name(): string
    {
        return 'wrong_namespace';
    }

    public function target(): string
    {
        return 'model';
    }

    public function expectedFailures(): array
    {
        return ['model.namespace'];
    }

    public function mutate(string $source): string
    {
        return preg_replace(
            '/namespace (App|Aicl)\\\\Models;/',
            'namespace Domain\\\\Entities;',
            $source,
        );
    }
}
