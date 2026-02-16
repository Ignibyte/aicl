@props([
    'options' => [],
    'value' => null,
    'placeholder' => 'Select...',
    'searchable' => true,
    'multiple' => false,
    'clearable' => false,
    'disabled' => false,
    'searchEndpoint' => null,
    'name' => null,
])

<div
    x-data="aiclCombobox({
        options: @js($options),
        value: @js($value),
        searchable: @js($searchable),
        multiple: @js($multiple),
        clearable: @js($clearable),
        disabled: @js($disabled),
        searchEndpoint: @js($searchEndpoint),
    })"
    {{ $attributes->merge(['class' => 'relative']) }}
    @click.outside="close()"
    @keydown.escape="close()"
>
    {{-- Hidden input for form submission --}}
    @if($name)
        <template x-if="!multiple">
            <input type="hidden" name="{{ $name }}" :value="selectedValue" />
        </template>
        @if($multiple)
            <template x-for="val in selectedValues" :key="val">
                <input type="hidden" name="{{ $name }}[]" :value="val" />
            </template>
        @endif
    @endif

    {{-- Input Wrapper --}}
    <div
        class="relative rounded-lg border bg-white transition-colors dark:bg-gray-900"
        :class="[
            isOpen ? 'border-primary-500 ring-1 ring-primary-500' : 'border-gray-300 dark:border-gray-600',
            disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
        ]"
        @click="!disabled && toggle()"
    >
        <div class="flex min-h-[42px] items-center gap-1 px-3 py-1.5">
            {{-- Selected Tags (multiple mode) --}}
            <template x-if="multiple && selectedOptions.length > 0">
                <div class="flex flex-wrap gap-1">
                    <template x-for="opt in selectedOptions" :key="opt.value">
                        <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
                            <span x-text="opt.label"></span>
                            <button type="button" @click.stop="deselect(opt.value)" class="hover:text-primary-900 dark:hover:text-primary-200" aria-label="Remove">
                                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </span>
                    </template>
                </div>
            </template>

            {{-- Search Input / Display --}}
            <input
                x-ref="input"
                type="text"
                :placeholder="displayPlaceholder"
                x-model="search"
                @focus="!disabled && open()"
                @input="!disabled && open()"
                :disabled="disabled"
                :readonly="!searchable"
                class="min-w-[60px] flex-1 border-0 bg-transparent p-0 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-white"
                role="combobox"
                aria-expanded="isOpen"
                aria-autocomplete="list"
                aria-controls="combobox-options"
                :aria-activedescendant="activeId"
                @keydown.arrow-down.prevent="moveDown()"
                @keydown.arrow-up.prevent="moveUp()"
                @keydown.enter.prevent="selectActive()"
            />

            {{-- Icons --}}
            <div class="flex shrink-0 items-center gap-1">
                <button
                    x-show="clearable && hasSelection"
                    x-cloak
                    type="button"
                    @click.stop="clearSelection()"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    aria-label="Clear selection"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
                <svg
                    class="h-4 w-4 text-gray-400 transition-transform"
                    :class="isOpen && 'rotate-180'"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                ><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </div>
        </div>
    </div>

    {{-- Dropdown --}}
    <div
        x-show="isOpen"
        x-cloak
        x-transition:enter="motion-safe:duration-150 motion-safe:ease-out"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="motion-safe:duration-100 motion-safe:ease-in"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        id="combobox-options"
        role="listbox"
        :aria-multiselectable="multiple"
        class="absolute z-50 mt-1 w-full rounded-lg border border-gray-200 bg-white py-1 shadow-md dark:border-gray-700 dark:bg-gray-800"
        style="max-height: 15rem; overflow-y: auto;"
    >
        {{-- Loading --}}
        <div x-show="loading" class="flex items-center justify-center px-3 py-3">
            <x-aicl-spinner size="sm" />
        </div>

        {{-- Options --}}
        <template x-for="(opt, index) in filteredOptions" :key="opt.value">
            <button
                type="button"
                role="option"
                :id="'combobox-opt-' + opt.value"
                :aria-selected="isSelected(opt.value)"
                @click="select(opt)"
                @mouseenter="activeIndex = index"
                :disabled="opt.disabled"
                class="flex w-full items-center gap-2 px-3 py-2 text-sm"
                :class="[
                    activeIndex === index ? 'bg-gray-100 dark:bg-gray-700' : '',
                    isSelected(opt.value) ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300',
                    opt.disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                ]"
            >
                <span x-text="opt.label" class="flex-1 text-left"></span>
                <svg x-show="isSelected(opt.value)" class="h-4 w-4 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </button>
        </template>

        {{-- No results --}}
        <div x-show="filteredOptions.length === 0 && !loading" class="px-3 py-2 text-sm italic text-gray-500">
            @if(isset($empty))
                {{ $empty }}
            @else
                No results found.
            @endif
        </div>
    </div>
</div>
