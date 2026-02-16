@props([
    'position' => 'right',
    'width' => 'md',
    'overlay' => true,
    'closeable' => true,
])

<div
    x-data="aiclDrawer({
        closeable: @js($closeable),
        position: @js($position),
    })"
    x-on:open-drawer.window="open()"
    x-on:close-drawer.window="close()"
    x-cloak
    {{ $attributes->merge(['class' => 'relative']) }}
>
    {{-- Backdrop --}}
    @if($overlay)
        <div
            x-show="isOpen"
            x-transition:enter="duration-200 ease-out"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="duration-150 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-black/50"
            @if($closeable)
                @click="close()"
            @endif
            aria-hidden="true"
        ></div>
    @endif

    {{-- Panel --}}
    <div
        x-show="isOpen"
        x-transition:enter="motion-safe:duration-200 motion-safe:ease-out"
        x-transition:enter-start="{{ $enterStart() }}"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="motion-safe:duration-150 motion-safe:ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="{{ $enterStart() }}"
        class="fixed {{ $positionClasses() }} z-50 flex h-full flex-col {{ $widthClass() }} bg-white shadow-xl dark:bg-gray-800"
        role="dialog"
        aria-modal="true"
        x-bind:aria-labelledby="$id('drawer-title')"
        @if($closeable)
            @keydown.escape.window="close()"
        @endif
        x-ref="panel"
    >
        {{-- Header --}}
        @if(isset($header))
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 x-bind:id="$id('drawer-title')" class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $header }}
                </h3>
                @if($closeable)
                    <button
                        type="button"
                        @click="close()"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:hover:bg-gray-700 dark:focus-visible:ring-offset-gray-800"
                        aria-label="Close"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                @endif
            </div>
        @endif

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-6 py-4">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @if(isset($footer))
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
