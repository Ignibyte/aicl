<?php

namespace Aicl\Search;

class SearchResult
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly string $url,
        public readonly string $icon,
        public readonly float $score,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $hit  Raw ES hit
     */
    public static function fromEsHit(array $hit): self
    {
        $source = $hit['_source'] ?? [];

        return new self(
            entityType: $source['entity_type'] ?? '',
            entityId: $source['entity_id'] ?? '',
            title: $source['title'] ?? '',
            subtitle: $source['body'] ?? null,
            url: $source['url'] ?? '',
            icon: $source['icon'] ?? 'heroicon-o-document',
            score: (float) ($hit['_score'] ?? 0.0),
            meta: $source['meta'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'icon' => $this->icon,
            'score' => $this->score,
            'meta' => $this->meta,
            'type' => class_basename($this->entityType),
            'type_icon' => $this->icon,
            'type_color' => 'primary',
        ];
    }
}
