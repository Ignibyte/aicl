<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Search Form --}}
        <form role="search" aria-label="Global search" class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        wire:model.live.debounce.300ms="query"
                        type="search"
                        placeholder="Search across all content..."
                        aria-label="Search query"
                        autofocus
                    />
                </x-filament::input.wrapper>
            </div>
            <div class="w-full sm:w-48">
                <x-filament::input.wrapper>
                    <x-filament::input.select
                        wire:model.live="entityType"
                        aria-label="Filter by entity type"
                    >
                        @foreach($this->getEntityTypes() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </form>

        @if(! config('aicl.search.enabled', false))
            <x-aicl-empty-state
                heading="Search Not Configured"
                description="Global search has not been enabled for this project."
                icon="heroicon-o-magnifying-glass"
            />
        @elseif(strlen($query) < config('aicl.search.min_query_length', 2))
            <x-aicl-empty-state
                heading="Start Searching"
                :description="'Enter at least ' . config('aicl.search.min_query_length', 2) . ' characters to search.'"
                icon="heroicon-o-magnifying-glass"
            />
        @else
            <div wire:loading.delay class="text-center py-8" role="status" aria-live="polite">
                <x-filament::loading-indicator class="mx-auto h-8 w-8 text-primary-500" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Searching...</p>
            </div>

            <div wire:loading.remove>
                @php $results = $this->searchResults; @endphp

                {{-- Facet Counts --}}
                @if(! empty($results->facets))
                    <nav aria-label="Filter by type" class="flex flex-wrap gap-2">
                        <button
                            wire:click="$set('entityType', '')"
                            aria-pressed="{{ empty($entityType) ? 'true' : 'false' }}"
                            @class([
                                'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900',
                                'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => empty($entityType),
                                'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10' => ! empty($entityType),
                            ])
                        >
                            All
                            <span class="text-xs opacity-75">({{ $results->total }})</span>
                        </button>
                        @foreach($results->facets as $type => $count)
                            @php $shortType = class_basename($type); @endphp
                            <button
                                wire:click="$set('entityType', '{{ $shortType }}')"
                                aria-pressed="{{ $entityType === $shortType ? 'true' : 'false' }}"
                                @class([
                                    'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900',
                                    'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => $entityType === $shortType,
                                    'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10' => $entityType !== $shortType,
                                ])
                            >
                                {{ str($shortType)->headline()->plural() }}
                                <span class="text-xs opacity-75">({{ $count }})</span>
                            </button>
                        @endforeach
                    </nav>
                @endif

                @if($results->isEmpty())
                    <x-aicl-empty-state
                        heading="No Results Found"
                        :description="'No results found for &quot;' . e($query) . '&quot;. Try a different search term.'"
                        icon="heroicon-o-document-magnifying-glass"
                    />
                @else
                    <div class="space-y-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400" aria-live="polite">
                            {{ $results->total }} result(s) found
                        </p>

                        <div class="divide-y divide-gray-200 dark:divide-gray-700 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            @foreach($results->results as $result)
                                <a
                                    href="{{ $result->url }}"
                                    aria-label="View {{ $result->title }}"
                                    class="flex items-start gap-4 p-4 transition first:rounded-t-xl last:rounded-b-xl hover:bg-gray-50 focus:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 dark:hover:bg-white/5 dark:focus:bg-white/5"
                                >
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-500/10">
                                            <x-filament::icon
                                                :icon="$result->icon"
                                                class="w-5 h-5 text-primary-600 dark:text-primary-400"
                                            />
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-sm font-medium text-gray-950 dark:text-white truncate">
                                                {{ $result->title }}
                                            </h4>
                                        </div>
                                        @if($result->subtitle)
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                                {{ $result->subtitle }}
                                            </p>
                                        @endif
                                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                            {{ str(class_basename($result->entityType))->headline() }}
                                        </p>
                                    </div>
                                    <x-filament::icon
                                        icon="heroicon-m-chevron-right"
                                        class="flex-shrink-0 w-5 h-5 text-gray-400 dark:text-gray-500"
                                    />
                                </a>
                            @endforeach
                        </div>

                        {{-- Pagination --}}
                        @if($results->totalPages() > 1)
                            <nav aria-label="Search results pagination" class="flex items-center justify-between pt-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Page {{ $results->page }} of {{ $results->totalPages() }}
                                </p>
                                <div class="flex gap-2">
                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        wire:click="goToPage({{ $results->page - 1 }})"
                                        :disabled="$results->page <= 1"
                                    >
                                        Previous
                                    </x-filament::button>
                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        wire:click="goToPage({{ $results->page + 1 }})"
                                        :disabled="! $results->hasMorePages()"
                                    >
                                        Next
                                    </x-filament::button>
                                </div>
                            </nav>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
