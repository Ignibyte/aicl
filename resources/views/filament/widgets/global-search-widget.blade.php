<x-filament-widgets::widget>
    <x-filament::section>
        <div class="relative" role="search" aria-label="Quick search">
            <x-filament::input.wrapper>
                <x-filament::input
                    wire:model.live.debounce.300ms="query"
                    type="search"
                    placeholder="Quick search..."
                    aria-label="Quick search"
                    wire:keydown.escape="clearSearch"
                />
            </x-filament::input.wrapper>

            {{-- Results Dropdown --}}
            @if($showResults)
                <div
                    class="absolute z-50 mt-2 w-full rounded-xl bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    wire:click.outside="clearSearch"
                    role="listbox"
                    aria-label="Search results"
                >
                    @if($this->results->isEmpty())
                        <div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No results found for "{{ $query }}"
                        </div>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700" role="list">
                            @foreach($this->results->results as $result)
                                <li>
                                    <a
                                        href="{{ $result->url }}"
                                        aria-label="View {{ $result->title }}"
                                        class="flex items-center gap-3 p-3 transition first:rounded-t-xl hover:bg-gray-50 focus:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 dark:hover:bg-white/5 dark:focus:bg-white/5"
                                    >
                                        <x-filament::icon
                                            :icon="$result->icon"
                                            class="w-5 h-5 text-gray-400 dark:text-gray-500"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-950 dark:text-white truncate">
                                                {{ $result->title }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ str(class_basename($result->entityType))->headline() }}@if($result->subtitle) &middot; {{ $result->subtitle }}@endif
                                            </p>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="border-t border-gray-200 p-2 dark:border-white/10">
                            <a
                                href="{{ \Aicl\Filament\Pages\Search::getUrl(['q' => $query]) }}"
                                class="block rounded-lg p-1.5 text-center text-sm font-medium text-primary-600 transition hover:bg-gray-50 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-primary-400 dark:hover:bg-white/5"
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
