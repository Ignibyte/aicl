@props([
    'label',
    'color' => 'gray',
    'variant' => 'soft',
    'size' => 'default',
    'shape' => 'full',
    'dot' => false,
    'removable' => false,
    'icon' => null,
])

<span
    {{ $attributes->merge(['class' => "inline-flex items-center gap-1 font-medium {$sizeClasses()} {$shapeClass()} {$colorClasses()}"]) }}
    @if($removable) x-data="{ visible: true }" x-show="visible" @endif
>
    @if($dot)
        <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif

    @if($icon)
        <x-filament::icon :icon="$icon" class="h-3.5 w-3.5 shrink-0" />
    @endif

    {{ $label }}

    @if($removable)
        <button
            type="button"
            @click="visible = false; $dispatch('badge-removed', { label: @js($label) })"
            class="ml-0.5 -mr-0.5 inline-flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-black/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-white/10"
            aria-label="Remove {{ $label }}"
        >
            <x-filament::icon icon="heroicon-m-x-mark" class="h-3 w-3" />
        </button>
    @endif
</span>
