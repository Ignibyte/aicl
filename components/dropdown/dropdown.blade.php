@props([
    'align' => 'bottom-start',
    'width' => 'auto',
    'closeOnClick' => true,
])

<div
    x-data="aiclDropdown({
        placement: @js($align),
        closeOnClick: @js($closeOnClick),
    })"
    {{ $attributes->merge(['class' => 'relative inline-block']) }}
    @keydown.escape="close()"
    @click.outside="close()"
>
    {{-- Trigger --}}
    <div
        x-ref="trigger"
        @click="toggle()"
        aria-haspopup="true"
        :aria-expanded="isOpen"
    >
        {{ $trigger }}
    </div>

    {{-- Panel --}}
    <div
        x-ref="panel"
        x-show="isOpen"
        x-cloak
        x-transition:enter="motion-safe:duration-150 motion-safe:ease-out"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="motion-safe:duration-100 motion-safe:ease-in"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 {{ $widthClass() }} rounded-lg border border-gray-200 bg-white py-1 shadow-md dark:border-gray-700 dark:bg-gray-800"
        role="menu"
        @if($closeOnClick)
            @click="close()"
        @endif
    >
        {{ $slot }}
    </div>
</div>
