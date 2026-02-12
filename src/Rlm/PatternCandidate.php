<?php

namespace Aicl\Rlm;

class PatternCandidate
{
    public function __construct(
        public string $name,
        public string $description,
        public string $target,
        public string $suggestedRegex,
        public string $severity = 'warning',
        public float $weight = 1.0,
        public float $confidence = 0.0,
        public int $occurrences = 0,
        public string $source = '',
    ) {}

    /**
     * Convert candidate to an EntityPattern for testing.
     */
    public function toEntityPattern(): EntityPattern
    {
        return new EntityPattern(
            name: $this->name,
            description: $this->description,
            target: $this->target,
            check: $this->suggestedRegex,
            severity: $this->severity,
            weight: $this->weight,
        );
    }

    /**
     * Export candidate as markdown for human review.
     */
    public function toMarkdown(): string
    {
        return implode("\n", [
            "### {$this->name}",
            '',
            "- **Description:** {$this->description}",
            "- **Target:** {$this->target}",
            "- **Regex:** `{$this->suggestedRegex}`",
            "- **Severity:** {$this->severity}",
            "- **Weight:** {$this->weight}",
            '- **Confidence:** '.number_format($this->confidence * 100, 1).'%',
            "- **Occurrences:** {$this->occurrences}",
            "- **Source:** {$this->source}",
            '',
        ]);
    }
}
