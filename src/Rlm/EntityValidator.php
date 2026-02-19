<?php

namespace Aicl\Rlm;

use Aicl\Models\EntityWaiver;

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

    protected bool $versionWarning = false;

    protected ?EntitySignature $signature = null;

    protected int $waivedCount = 0;

    protected float $waivedWeight = 0.0;

    public function __construct(
        protected string $entityName,
        protected ?string $patternVersion = null,
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
     * Set the entity feature signature for enhanced validation context.
     */
    public function setSignature(EntitySignature $signature): static
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * Get the entity feature signature, if set.
     */
    public function getSignature(): ?EntitySignature
    {
        return $this->signature;
    }

    /**
     * Run all pattern checks and return results.
     *
     * @param  array<int, string>|null  $targets  If provided, only validate patterns matching these targets
     * @return array<int, ValidationResult>
     */
    public function validate(?array $targets = null): array
    {
        $this->results = [];
        $this->versionWarning = false;
        $this->waivedCount = 0;
        $this->waivedWeight = 0.0;

        if ($this->patternVersion !== null) {
            $patterns = PatternRegistry::getPatternSet($this->patternVersion, $this->entityName);
        } else {
            $patterns = PatternRegistry::all($this->entityName);
            $this->versionWarning = true;
        }

        // Filter by targets if specified
        if ($targets !== null && $targets !== []) {
            $patterns = array_values(array_filter(
                $patterns,
                fn (EntityPattern $p): bool => in_array($p->target, $targets, true),
            ));
        }

        // Load active waivers for this entity
        $waivers = $this->loadActiveWaivers();

        foreach ($patterns as $pattern) {
            // Check for waiver before file check
            if (isset($waivers[$pattern->name])) {
                $waiver = $waivers[$pattern->name];
                $this->waivedCount++;
                $this->waivedWeight += $pattern->weight;
                $this->results[] = new ValidationResult(
                    pattern: $pattern,
                    passed: true,
                    message: "WAIVED ({$waiver->reason})",
                    waived: true,
                );

                continue;
            }

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

    /**
     * Whether validation used unpinned (latest) patterns.
     */
    public function hasVersionWarning(): bool
    {
        return $this->versionWarning;
    }

    /**
     * Get the pattern version used for validation.
     */
    public function patternVersion(): string
    {
        return $this->patternVersion ?? PatternRegistry::currentVersion();
    }

    /**
     * Get count of waived patterns.
     */
    public function waivedCount(): int
    {
        return $this->waivedCount;
    }

    /**
     * Get total weight consumed by waivers.
     */
    public function waivedWeight(): float
    {
        return $this->waivedWeight;
    }

    /**
     * Get remaining waiver budget for this entity.
     */
    public function remainingBudget(): float
    {
        $budget = (float) config('aicl.rlm.waiver_budget', 5.0);

        return max(0, $budget - $this->waivedWeight);
    }

    /**
     * Load active (non-expired) waivers for this entity, keyed by pattern_id.
     *
     * @return array<string, EntityWaiver>
     */
    private function loadActiveWaivers(): array
    {
        try {
            return EntityWaiver::query()
                ->forEntity($this->entityName)
                ->active()
                ->get()
                ->keyBy('pattern_id')
                ->all();
        } catch (\Throwable) {
            // Table may not exist yet (pre-migration)
            return [];
        }
    }
}
