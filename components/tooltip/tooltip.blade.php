@props([
    'content',
    'position' => 'top',
    'delay' => 200,
])

<div
    x-data="aiclTooltip({
        position: @js($position),
        delay: @js($delay),
    })"
    {{ $attributes->merge(['class' => 'relative inline-flex']) }}
    @mouseenter="show()"
    @mouseleave="hide()"
    @focusin="show()"
    @focusout="hide()"
    @keydown.escape="hide()"
>
    {{-- Trigger --}}
    <div x-ref="trigger" :aria-describedby="$id('tooltip')">
        {{ $slot }}
    </div>

    {{-- Tooltip --}}
    <div
        x-ref="tooltip"
        x-show="isVisible"
        x-cloak
        x-transition:enter="motion-safe:duration-150 motion-safe:ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="motion-safe:duration-100 motion-safe:ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        :id="$id('tooltip')"
        role="tooltip"
        class="absolute z-50 max-w-xs rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-md dark:bg-gray-700 dark:text-gray-100"
    >
        {{ $content }}
        <div x-ref="arrow" class="absolute h-2 w-2 rotate-45 bg-gray-900 dark:bg-gray-700"></div>
    </div>
</div>
