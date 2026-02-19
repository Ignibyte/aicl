<?php

namespace Aicl\Rlm\Mutators;

/**
 * G.5: Removes the fillable property from a model.
 *
 * L1 pattern `model.fillable` checks for `protected $fillable`.
 * This mutator removes the entire $fillable declaration.
 */
class MutatorMissingSearchableColumns implements Mutator
{
    public function name(): string
    {
        return 'missing_fillable';
    }

    public function target(): string
    {
        return 'model';
    }

    public function expectedFailures(): array
    {
        return ['model.fillable'];
    }

    public function mutate(string $source): string
    {
        // Remove the protected $fillable = [...]; block (may span multiple lines)
        return preg_replace(
            '/\s*protected \$fillable = \[.*?\];\s*/s',
            "\n",
            $source,
        );
    }
}
