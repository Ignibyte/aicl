<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * 50/50 split authentication layout with form on left and image on right.
 *
 * AI Decision Rules:
 * - Use for login, registration, and password reset pages
 * - Right side is hidden on mobile (< lg breakpoint)
 * - Provide a background image URL or fall back to branded gradient
 * - Overlay content (slot) is centered over the background image
 */
class AuthSplitLayout extends Component
{
    public function __construct(
        public ?string $backgroundImage = null,
        public string $overlayOpacity = '30',
    ) {}

    public function render(): View
    {
        return view('aicl::components.auth-split-layout');
    }
}
