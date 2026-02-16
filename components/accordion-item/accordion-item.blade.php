@props([
    'name',
    'label',
    'icon' => null,
])

<div {{ $attributes }}>
    {{-- Header --}}
    <button
        type="button"
        @click="toggle('{{ $name }}')"
        :aria-expanded="isOpen('{{ $name }}')"
        aria-controls="accordion-content-{{ $name }}"
        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-gray-900 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500 dark:text-white dark:hover:bg-gray-800"
    >
        <span class="flex items-center gap-2">
            @if($icon)
                <x-filament::icon :icon="$icon" class="h-5 w-5 text-gray-400" />
            @endif
            {{ $label }}
        </span>

        <x-filament::icon
            icon="heroicon-m-chevron-down"
            class="h-5 w-5 text-gray-400 transition-transform duration-200"
            ::class="isOpen('{{ $name }}') && 'rotate-180'"
        />
    </button>

    {{-- Content --}}
    <div
        x-show="isOpen('{{ $name }}')"
        x-cloak
        x-collapse.duration.200ms
        id="accordion-content-{{ $name }}"
        role="region"
        aria-labelledby="accordion-header-{{ $name }}"
    >
        <div class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ $slot }}
        </div>
    </div>
</div>
