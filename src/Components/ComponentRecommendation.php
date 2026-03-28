<?php

declare(strict_types=1);

namespace Aicl\Components;

/**
 * Readonly value object for AI component recommendations.
 *
 * Returned by ComponentRegistry::recommend() and recommendForEntity()
 * to suggest which components to use for given fields/contexts.
 */
class ComponentRecommendation
{
    /**
     * @param  array<string, mixed>  $suggestedProps
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $reason,
        public readonly array $suggestedProps,
        public readonly float $confidence,
        public readonly ?string $alternative,
    ) {}

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tag' => $this->tag,
            'reason' => $this->reason,
            'suggestedProps' => $this->suggestedProps,
            'confidence' => $this->confidence,
            'alternative' => $this->alternative,
        ];
    }
}
