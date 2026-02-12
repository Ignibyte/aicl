<?php

namespace Aicl\Rlm;

class SemanticCheck
{
    public function __construct(
        public string $name,
        public string $description,
        /** @var string[] */
        public array $targets,
        public string $prompt,
        public string $severity = 'warning',
        public float $weight = 1.5,
        public ?string $appliesWhen = null,
    ) {}

    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }

    /**
     * Check if this semantic check applies given the entity context.
     *
     * @param  array<string, mixed>  $entityContext
     */
    public function isApplicable(array $entityContext = []): bool
    {
        if ($this->appliesWhen === null) {
            return true;
        }

        return ! empty($entityContext[$this->appliesWhen]);
    }
}
