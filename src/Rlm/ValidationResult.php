<?php

namespace Aicl\Rlm;

/**
 * Result of validating a single pattern against entity code.
 */
class ValidationResult
{
    public function __construct(
        public EntityPattern $pattern,
        public bool $passed,
        public string $message = '',
        public ?string $file = null,
    ) {}
}
