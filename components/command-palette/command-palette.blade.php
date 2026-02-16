@props([
    'items' => [],
    'placeholder' => 'Search...',
    'groups' => [],
    'searchEndpoint' => null,
])

<div
    x-data="aiclCommandPalette({
        items: @js($items),
        groups: @js($groups),
        searchEndpoint: @js($searchEndpoint),
    })"
    @keydown.window="handleGlobalKeydown($event)"
    x-cloak
    {{ $attributes }}
>
    {{-- Backdrop --}}
    <div
        x-show="isOpen"
        x-transition:enter="duration-200 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/50"
        @click="close()"
        aria-hidden="true"
    ></div>

    {{-- Panel --}}
    <div
        x-show="isOpen"
        x-transition:enter="motion-safe:duration-200 motion-safe:ease-out"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="motion-safe:duration-150 motion-safe:ease-in"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed left-1/2 top-[20%] z-50 w-full max-w-xl -translate-x-1/2 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
        role="dialog"
        aria-modal="true"
        aria-label="Command palette"
        @keydown.escape="close()"
        @keydown.arrow-down.prevent="moveDown()"
        @keydown.arrow-up.prevent="moveUp()"
        @keydown.enter.prevent="selectActive()"
    >
        {{-- Search Input --}}
        <div class="flex items-center border-b border-gray-200 dark:border-gray-700">
            <svg class="ml-4 h-5 w-5 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
            <input
                type="text"
                x-ref="searchInput"
                x-model.debounce.150ms="query"
                placeholder="{{ $placeholder }}"
                class="w-full border-0 bg-transparent px-3 py-3 text-base text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-white"
                role="combobox"
                aria-expanded="true"
                aria-autocomplete="list"
                aria-controls="command-results"
                :aria-activedescendant="activeId"
            />
        </div>

        {{-- Results --}}
        <div id="command-results" role="listbox" class="max-h-80 overflow-y-auto py-2">
            <template x-if="filteredItems.length === 0 && query.length > 0">
                <div class="px-4 py-8 text-center text-sm text-gray-500">
                    No results found.
                </div>
            </template>

            <template x-for="(group, gIndex) in groupedItems" :key="group.key">
                <div>
                    <div x-show="group.label" class="px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500" x-text="group.label"></div>
                    <template x-for="(item, iIndex) in group.items" :key="item.id">
                        <button
                            type="button"
                            role="option"
                            :id="'cmd-item-' + item.id"
                            :aria-selected="activeIndex === item._flatIndex"
                            @click="select(item)"
                            @mouseenter="activeIndex = item._flatIndex"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300"
                            :class="activeIndex === item._flatIndex ? 'bg-gray-100 dark:bg-gray-700' : ''"
                        >
                            <template x-if="item.icon">
                                <svg class="h-5 w-5 shrink-0 text-gray-400"><use :href="'#' + item.icon"></use></svg>
                            </template>
                            <span x-text="item.label" class="flex-1 text-left"></span>
                            <template x-if="item.shortcut">
                                <span class="ml-auto rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-400 dark:bg-gray-700" x-text="item.shortcut"></span>
                            </template>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="border-t border-gray-200 px-4 py-2 text-xs text-gray-400 dark:border-gray-700">
            @if(isset($footer))
                {{ $footer }}
            @else
                <span class="inline-flex items-center gap-2">
                    <kbd class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-700">&uarr;&darr;</kbd> Navigate
                    <kbd class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-700">&crarr;</kbd> Select
                    <kbd class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-700">Esc</kbd> Close
                </span>
            @endif
        </div>
    </div>
</div>
