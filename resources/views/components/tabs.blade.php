@props(['defaultTab' => '', 'variant' => 'underline'])

<div
    x-data="{
        activeTab: '{{ $defaultTab }}',
        tabs: [],
        init() {
            this.$el.querySelectorAll('[data-tab-name]').forEach(panel => {
                this.tabs.push({
                    name: panel.dataset.tabName,
                    label: panel.dataset.tabLabel,
                });
            });
            if (!this.activeTab && this.tabs.length) {
                this.activeTab = this.tabs[0].name;
            }
        }
    }"
    {{ $attributes->merge(['class' => 'w-full']) }}
>
    {{-- Tab Buttons --}}
    <div
        @if($variant === 'underline')
            class="border-b border-gray-200 dark:border-gray-700"
        @else
            class="mb-4"
        @endif
        role="tablist"
    >
        <nav
            @if($variant === 'underline')
                class="-mb-px flex space-x-6"
            @else
                class="flex flex-wrap gap-2"
            @endif
        >
            <template x-for="tab in tabs" :key="tab.name">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.name"
                    :aria-controls="'tab-panel-' + tab.name"
                    @click="activeTab = tab.name"
                    @if($variant === 'underline')
                        :class="activeTab === tab.name
                            ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="border-b-2 px-1 py-3 text-sm font-medium transition-colors"
                    @else
                        :class="activeTab === tab.name
                            ? 'bg-primary-500 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    @endif
                    x-text="tab.label"
                ></button>
            </template>
        </nav>
    </div>

    {{-- Tab Panels --}}
    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
