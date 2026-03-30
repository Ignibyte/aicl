<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Avatar with image, initials fallback, and optional status indicator.
 *
 * AI Decision Rules:
 * - Use for user/profile display in cards, lists, and headers
 * - Provide 'name' for initials fallback when image may not exist
 * - Use 'status' for online/offline indicators in presence UIs
 * - For grouped avatars, wrap multiple avatars in a flex container with -space-x-2
 */
class Avatar extends Component
{
    public function __construct(
        public ?string $src = null,
        public string $alt = '',
        public ?string $name = null,
        public string $size = 'md',
        public string $rounded = 'full',
        public ?string $status = null,
    ) {}

    public function sizeClasses(): string
    {
        return match ($this->size) {
            'xs' => 'h-6 w-6 text-[10px]',
            'sm' => 'h-8 w-8 text-xs',
            'lg' => 'h-12 w-12 text-base',
            'xl' => 'h-16 w-16 text-lg',
            default => 'h-10 w-10 text-sm',
        };
    }

    public function roundedClass(): string
    {
        return $this->rounded === 'lg' ? 'rounded-lg' : 'rounded-full';
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'online' => 'bg-green-500',
            'busy' => 'bg-red-500',
            'away' => 'bg-yellow-500',
            'offline' => 'bg-gray-400',
            default => 'bg-gray-400',
        };
    }

    public function statusDotSize(): string
    {
        return match ($this->size) {
            'xs', 'sm' => 'h-2 w-2',
            'lg', 'xl' => 'h-3 w-3',
            default => 'h-2.5 w-2.5',
        };
    }

    public function initials(): string
    {
        if (! $this->name) {
            return '?';
        }

        $words = preg_split('/\s+/', trim($this->name));
        if (count($words) >= 2) {
            return strtoupper(mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1));
        }

        return strtoupper(mb_substr($words[0], 0, 2));
    }

    public function render(): View
    {
        return view('aicl::components.avatar');
    }
}
