<?php

namespace Aicl\Rlm;

class SemanticResult
{
    public function __construct(
        public SemanticCheck $check,
        public bool $passed,
        public string $message = '',
        public float $confidence = 1.0,
        /** @var string[] */
        public array $files = [],
        public bool $skipped = false,
    ) {}
}
