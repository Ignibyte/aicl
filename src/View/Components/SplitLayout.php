<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Two-column split layout with configurable ratio.
 *
 * AI Decision Rules:
 * - Use when a page has a main content area + contextual sidebar
 * - Default ratio 2/3 + 1/3 for detail views
 * - Use ratio 3/4 + 1/4 for content-heavy pages with metadata sidebar
 * - Reverse (sidebar-first) for navigation-heavy layouts
 *
 * @example <x-aicl-split-layout>
 *     <x-slot:main>Main content</x-slot:main>
 *     <x-slot:sidebar>Sidebar content</x-slot:sidebar>
 * </x-aicl-split-layout>
 */
class SplitLayout extends Component
{
    public function __construct(
        public string $ratio = '2/3',
        public bool $reverse = false,
    ) {}

    public function mainCols(): string
    {
        return match ($this->ratio) {
            '3/4' => 'lg:col-span-9',
            '1/2' => 'lg:col-span-6',
            default => 'lg:col-span-8',
        };
    }

    public function sidebarCols(): string
    {
        return match ($this->ratio) {
            '3/4' => 'lg:col-span-3',
            '1/2' => 'lg:col-span-6',
            default => 'lg:col-span-4',
        };
    }

    public function render(): View
    {
        return view('aicl::components.split-layout');
    }
}
