<?php

declare(strict_types=1);

namespace Aicl\Console\Generators;

use Aicl\Console\Support\EntityGeneratorContext;

/**
 * Base class for entity generators providing shared utilities.
 */
abstract class BaseGenerator implements EntityGenerator
{
    public function __construct(
        protected EntityGeneratorContext $ctx,
    ) {}

    /**
     * Ensure a directory exists, creating it recursively if needed.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
