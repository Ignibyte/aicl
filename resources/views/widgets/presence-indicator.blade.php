<x-filament-widgets::widget>
    <div
        x-data="presenceIndicator({
            channelName: @js($this->channelName),
        })"
        x-init="init()"
        x-show="viewers.length > 0"
        x-cloak
    >
        <div class="flex items-center gap-1 flex-wrap">
            <span class="text-xs text-gray-500 dark:text-gray-400 mr-1">Viewing:</span>
            <template x-for="viewer in viewers" :key="viewer.id">
                <span
                    class="inline-flex items-center rounded-full bg-primary-50 dark:bg-primary-500/10 px-2 py-0.5 text-xs font-medium text-primary-700 dark:text-primary-400"
                    x-text="viewer.name"
                ></span>
            </template>
        </div>
    </div>
</x-filament-widgets::widget>
