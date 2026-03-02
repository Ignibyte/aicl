<x-filament-panels::page>
    <div class="grid gap-6 sm:grid-cols-2">
        @foreach($this->getCards() as $card)
            <a
                href="{{ $card['url'] }}"
                class="group block rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 transition hover:shadow-md hover:ring-primary-500/25 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-400/25"
            >
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                        <x-filament::icon
                            :icon="$card['icon']"
                            class="h-6 w-6"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                            {{ $card['title'] }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $card['description'] }}
                        </p>
                    </div>

                    <x-filament::icon
                        icon="heroicon-o-chevron-right"
                        class="h-5 w-5 shrink-0 text-gray-400 transition group-hover:text-primary-600 dark:text-gray-500 dark:group-hover:text-primary-400"
                    />
                </div>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
