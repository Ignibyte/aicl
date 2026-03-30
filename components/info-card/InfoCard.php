<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Card with heading and key-value content.
 *
 * AI Decision Rules:
 * - Use for grouped metadata sections within a detail page
 * - Use inside CardGrid for multiple info sections side by side
 * - Combines a card wrapper with MetadataList-style content
 *
 * @example <x-aicl-info-card heading="Project Details" :items="['Status' => 'Active', 'Priority' => 'High']" />
 */
class InfoCard extends Component
{
    /**
     * @param array<string, string|null> $items
     */
    public function __construct(
        public string $heading,
        public array $items = [],
        public ?string $icon = null,
    ) {}

    public function render(): View
    {
        return view('aicl::components.info-card');
    }
}
