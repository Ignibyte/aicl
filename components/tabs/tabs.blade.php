@props(['defaultTab' => '', 'variant' => 'underline', 'hashSync' => false])

<div
    x-data="{
        activeTab: '{{ $defaultTab }}',
        tabs: [],
        hashSync: {{ $hashSync ? 'true' : 'false' }},
        init() {
            this.$el.querySelectorAll('[data-tab-name]').forEach(panel => {
                this.tabs.push({
                    name: panel.dataset.tabName,
                    label: panel.dataset.tabLabel,
                    icon: panel.dataset.tabIcon || null,
                    badge: panel.dataset.tabBadge || null,
                    disabled: panel.dataset.tabDisabled === 'true',
                });
            });

            if (this.hashSync && window.location.hash) {
                const hash = window.location.hash.substring(1);
                const found = this.tabs.find(t => t.name === hash && !t.disabled);
                if (found) this.activeTab = found.name;
            }

            if (!this.activeTab && this.tabs.length) {
                const first = this.tabs.find(t => !t.disabled);
                if (first) this.activeTab = first.name;
            }
        },
        switchTab(name) {
            const tab = this.tabs.find(t => t.name === name);
            if (tab && tab.disabled) return;
            this.activeTab = name;
            if (this.hashSync) {
                window.history.replaceState(null, '', '#' + name);
            }
        }
    }"
    {{ $attributes->merge(['class' => 'w-full']) }}
>
    @if($variant === 'vertical')
        <div class="flex gap-6">
            {{-- Vertical Tab List --}}
            <div class="flex w-48 shrink-0 flex-col border-r border-gray-200 pr-4 dark:border-gray-700" role="tablist" aria-orientation="vertical">
                <template x-for="tab in tabs" :key="tab.name">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.name"
                        :aria-controls="'tab-panel-' + tab.name"
                        :aria-disabled="tab.disabled"
                        @click="switchTab(tab.name)"
                        @keydown.arrow-down.prevent="$event.target.nextElementSibling?.focus()"
                        @keydown.arrow-up.prevent="$event.target.previousElementSibling?.focus()"
                        @keydown.home.prevent="$event.target.parentElement.firstElementChild?.focus()"
                        @keydown.end.prevent="$event.target.parentElement.lastElementChild?.focus()"
                        :class="[
                            activeTab === tab.name
                                ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400'
                                : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800',
                            tab.disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                        ]"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                    >
                        <span x-text="tab.label"></span>
                        <template x-if="tab.badge">
                            <span class="ml-auto inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium dark:bg-gray-700" x-text="tab.badge"></span>
                        </template>
                    </button>
                </template>
            </div>

            {{-- Tab Panels --}}
            <div class="min-w-0 flex-1">
                {{ $slot }}
            </div>
        </div>
    @else
        {{-- Horizontal Tabs --}}
        <div
            @if($variant === 'underline')
                class="border-b border-gray-200 dark:border-gray-700"
            @elseif($variant === 'boxed')
                class="mb-4 rounded-lg bg-gray-100 p-1 dark:bg-gray-800"
            @else
                class="mb-4"
            @endif
            role="tablist"
            aria-orientation="horizontal"
        >
            <nav
                @if($variant === 'underline')
                    class="-mb-px flex space-x-6"
                @elseif($variant === 'boxed')
                    class="flex"
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
                        :aria-disabled="tab.disabled"
                        @click="switchTab(tab.name)"
                        @keydown.arrow-right.prevent="$event.target.nextElementSibling?.focus()"
                        @keydown.arrow-left.prevent="$event.target.previousElementSibling?.focus()"
                        @keydown.home.prevent="$event.target.parentElement.firstElementChild?.focus()"
                        @keydown.end.prevent="$event.target.parentElement.lastElementChild?.focus()"
                        @if($variant === 'underline')
                            :class="[
                                activeTab === tab.name
                                    ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300',
                                tab.disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                            class="flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
                        @elseif($variant === 'boxed')
                            :class="[
                                activeTab === tab.name
                                    ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300',
                                tab.disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                            class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-center text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800"
                        @else
                            :class="[
                                activeTab === tab.name
                                    ? 'bg-primary-500 text-white shadow-sm'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700',
                                tab.disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                        @endif
                    >
                        <span x-text="tab.label"></span>
                        <template x-if="tab.badge">
                            <span class="ml-1 inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium dark:bg-gray-700" x-text="tab.badge"></span>
                        </template>
                    </button>
                </template>
            </nav>
        </div>

        {{-- Tab Panels --}}
        <div class="mt-4">
            {{ $slot }}
        </div>
    @endif
</div>
