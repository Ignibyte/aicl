<x-filament-widgets::widget>
    <x-filament::section>
        <div
            x-data="pollingWidget({
                interval: @js($this->pollingInterval()),
                pauseWhenHidden: @js($this->pauseWhenHidden()),
            })"
            x-init="init()"
        >
            <div
                x-show="paused"
                x-cloak
                class="text-xs text-gray-400 dark:text-gray-500 mb-2 flex items-center gap-1"
            >
                <x-filament::icon
                    icon="heroicon-s-pause-circle"
                    class="h-4 w-4"
                />
                Paused
            </div>
            {{ $slot ?? '' }}
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
