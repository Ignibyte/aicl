<?php

namespace Aicl\Rlm\Mutators;

/**
 * Base interface for code mutators used in mutation testing.
 *
 * Mutators take valid PHP source code and introduce specific defects
 * that L1 validation patterns should detect.
 */
interface Mutator
{
    /**
     * Human-readable name for the mutation.
     */
    public function name(): string;

    /**
     * Which file target this mutator operates on (model, policy, factory, etc.).
     */
    public function target(): string;

    /**
     * Pattern IDs this mutation should trigger as failures.
     *
     * @return array<int, string>
     */
    public function expectedFailures(): array;

    /**
     * Apply the mutation to source code.
     */
    public function mutate(string $source): string;
}
