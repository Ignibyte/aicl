<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Code snippet display with copy-to-clipboard and show/hide toggle.
 *
 * AI Decision Rules:
 * - Use in styleguide pages to show Blade markup alongside live demos
 * - Use in documentation views for copy-paste code examples
 * - Pass code as a raw string via the :code prop
 */
class CodeBlock extends Component
{
    public function __construct(
        public string $code,
        public string $language = 'blade',
    ) {}

    public function render(): View
    {
        return view('aicl::components.code-block');
    }
}
