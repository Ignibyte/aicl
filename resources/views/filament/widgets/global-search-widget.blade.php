<x-filament-widgets::widget>
    <x-filament::section>
        <div class="relative">
            <x-filament::input.wrapper>
                <x-filament::input
                    wire:model.live.debounce.300ms="query"
                    type="search"
                    placeholder="Quick search..."
                    wire:keydown.escape="clearSearch"
                />
            </x-filament::input.wrapper>

            {{-- Results Dropdown --}}
            @if($showResults)
                <div
                    class="absolute z-50 mt-2 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg"
                    wire:click.outside="clearSearch"
                >
                    @if($this->results->isEmpty())
                        <div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No results found for "{{ $query }}"
                        </div>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->results as $result)
                                <li>
                                    <a
                                        href="{{ $result['url'] }}"
                                        class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                                    >
                                        <x-filament::icon
                                            :icon="$result['icon']"
                                            class="w-5 h-5 text-gray-400"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                {{ $result['title'] }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $result['type'] }} · {{ $result['subtitle'] }}
                                            </p>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="border-t border-gray-200 dark:border-gray-700 p-2">
                            <a
                                href="{{ route('filament.admin.pages.search', ['query' => $query]) }}"
                                class="block text-center text-sm text-primary-600 dark:text-primary-400 hover:underline"
                            >
                                See all results
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
