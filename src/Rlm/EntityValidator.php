<?php

namespace Aicl\Rlm;

/**
 * Validates AI-generated entity code against AICL patterns.
 *
 * Reads the source files for an entity and scores them against the
 * pattern registry. Returns a validation report with pass/fail status,
 * score percentage, and individual pattern results.
 */
class EntityValidator
{
    /**
     * @var array<string, string>
     */
    protected array $files = [];

    /**
     * @var array<int, ValidationResult>
     */
    protected array $results = [];

    public function __construct(
        protected string $entityName,
    ) {}

    /**
     * Register a source file for a given target type.
     */
    public function addFile(string $target, string $path): static
    {
        $this->files[$target] = $path;

        return $this;
    }

    /**
     * Run all pattern checks and return results.
     *
     * @return array<int, ValidationResult>
     */
    public function validate(): array
    {
        $this->results = [];
        $patterns = PatternRegistry::all($this->entityName);

        foreach ($patterns as $pattern) {
            if (! isset($this->files[$pattern->target])) {
                continue;
            }

            $filePath = $this->files[$pattern->target];

            if (! file_exists($filePath)) {
                $this->results[] = new ValidationResult(
                    pattern: $pattern,
                    passed: false,
                    message: "File not found: {$filePath}",
                    file: $filePath,
                );

                continue;
            }

            $content = file_get_contents($filePath); // nosemgrep: file-get-contents-url

            if ($content === false) {
                $this->results[] = new ValidationResult(
                    pattern: $pattern,
                    passed: false,
                    message: "Could not read file: {$filePath}",
                    file: $filePath,
                );

                continue;
            }

            $passed = (bool) preg_match('/'.$pattern->check.'/', $content);

            $this->results[] = new ValidationResult(
                pattern: $pattern,
                passed: $passed,
                message: $passed ? 'Pattern matched' : "Missing: {$pattern->description}",
                file: $filePath,
            );
        }

        return $this->results;
    }

    /**
     * Calculate overall score as a percentage.
     */
    public function score(): float
    {
        if (empty($this->results)) {
            $this->validate();
        }

        $totalWeight = 0.0;
        $earnedWeight = 0.0;

        foreach ($this->results as $result) {
            $totalWeight += $result->pattern->weight;
            if ($result->passed) {
                $earnedWeight += $result->pattern->weight;
            }
        }

        if ($totalWeight === 0.0) {
            return 100.0;
        }

        return round(($earnedWeight / $totalWeight) * 100, 1);
    }

    /**
     * Check if validation has any errors (not warnings).
     */
    public function hasErrors(): bool
    {
        foreach ($this->results as $result) {
            if (! $result->passed && $result->pattern->isError()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get only failed results.
     *
     * @return array<int, ValidationResult>
     */
    public function failures(): array
    {
        return array_filter(
            $this->results,
            fn (ValidationResult $r): bool => ! $r->passed,
        );
    }

    /**
     * Get only error-level failures.
     *
     * @return array<int, ValidationResult>
     */
    public function errors(): array
    {
        return array_filter(
            $this->results,
            fn (ValidationResult $r): bool => ! $r->passed && $r->pattern->isError(),
        );
    }

    /**
     * Get only warning-level failures.
     *
     * @return array<int, ValidationResult>
     */
    public function warnings(): array
    {
        return array_filter(
            $this->results,
            fn (ValidationResult $r): bool => ! $r->passed && $r->pattern->isWarning(),
        );
    }

    /**
     * @return array<int, ValidationResult>
     */
    public function results(): array
    {
        return $this->results;
    }
}
