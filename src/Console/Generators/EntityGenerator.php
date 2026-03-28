<?php

declare(strict_types=1);

namespace Aicl\Console\Generators;

/**
 * Contract for entity artifact generators.
 *
 * Each generator produces one or more files for a specific entity artifact
 * (model, migration, factory, etc.) and returns their relative paths.
 */
interface EntityGenerator
{
    /**
     * Human-readable task label for CLI output.
     */
    public function label(): string;

    /**
     * Generate files and return array of relative file paths.
     *
     * @return array<int, string>
     */
    public function generate(): array;
}
