<x-filament-panels::page>
    <div class="mx-auto flex max-w-lg flex-col items-center justify-center py-16 text-center">
        <div class="mb-6">
            <span class="text-8xl font-extrabold text-gray-200 dark:text-gray-800">
                {{ $code }}
            </span>
        </div>

        <div class="mb-4 rounded-full bg-gray-100 p-4 dark:bg-gray-800">
            <x-filament::icon :icon="$icon" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
        </div>

        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ $this->getTitle() }}
        </h3>

        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ $description }}
        </p>

        <div class="mt-8 flex items-center gap-3">
            <x-filament::button
                :href="filament()->getUrl()"
                tag="a"
                icon="heroicon-o-home"
            >
                Go to Dashboard
            </x-filament::button>

            <x-filament::button
                color="gray"
                tag="a"
                icon="heroicon-o-arrow-left"
                onclick="history.back(); return false;"
                href="#"
            >
                Go Back
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
