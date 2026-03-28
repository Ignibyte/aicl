<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Ignibyte brand logo component.
 *
 * Displays the Ignibyte logo image with optional "IGNIBYTE" text.
 * Supports multiple sizes and icon-only mode for collapsed sidebar.
 *
 * Usage:
 *   <x-aicl-ignibyte-logo />
 *   <x-aicl-ignibyte-logo size="sm" />
 *   <x-aicl-ignibyte-logo size="lg" icon-only />
 *
 * @codeCoverageIgnore Blade view component
 */
class IgnibyteLogo extends Component
{
    public function __construct(
        public string $size = 'md',
        public bool $iconOnly = false,
    ) {}

    public function logoHeight(): string
    {
        return match ($this->size) {
            'sm' => 'h-6',
            'md' => 'h-10',
            'lg' => 'h-16',
            'xl' => 'h-24',
            default => 'h-10',
        };
    }

    public function textSize(): string
    {
        return match ($this->size) {
            'sm' => 'text-xl',
            'md' => 'text-3xl',
            'lg' => 'text-5xl',
            'xl' => 'text-7xl',
            default => 'text-3xl',
        };
    }

    public function logoUrl(): string
    {
        return asset(config('aicl.theme.logo', 'vendor/aicl/images/logo.png'));
    }

    public function brandName(): string
    {
        return config('aicl.theme.brand_name', 'IGNIBYTE');
    }

    public function render(): View
    {
        return view('aicl::components.ignibyte-logo');
    }
}
