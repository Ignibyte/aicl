<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Search Form --}}
        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        wire:model.live.debounce.300ms="query"
                        type="search"
                        placeholder="Search projects, tasks, and more..."
                        autofocus
                    />
                </x-filament::input.wrapper>
            </div>
            <div class="w-full sm:w-48">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="entityType">
                        @foreach($this->getEntityTypes() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        {{-- Results --}}
        @if(strlen($query) < 2)
            <div class="text-center py-12">
                <x-filament::icon
                    icon="heroicon-o-magnifying-glass"
                    class="mx-auto h-12 w-12 text-gray-400"
                />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                    Start Searching
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Enter at least 2 characters to search.
                </p>
            </div>
        @elseif($this->results->isEmpty())
            <div class="text-center py-12">
                <x-filament::icon
                    icon="heroicon-o-document-magnifying-glass"
                    class="mx-auto h-12 w-12 text-gray-400"
                />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                    No Results Found
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No results found for "{{ $query }}". Try a different search term.
                </p>
            </div>
        @else
            <div class="space-y-2">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Found {{ $this->results->count() }} result(s)
                </p>

                <div class="divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    @foreach($this->results as $result)
                        <a
                            href="{{ $result['url'] }}"
                            class="flex items-start gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                        >
                            <div class="flex-shrink-0">
                                <span @class([
                                    'inline-flex items-center justify-center w-10 h-10 rounded-lg',
                                    'bg-primary-100 dark:bg-primary-900' => $result['type_color'] === 'primary',
                                    'bg-gray-100 dark:bg-gray-800' => $result['type_color'] !== 'primary',
                                ])>
                                    <x-filament::icon
                                        :icon="$result['type_icon']"
                                        @class([
                                            'w-5 h-5',
                                            'text-primary-600 dark:text-primary-400' => $result['type_color'] === 'primary',
                                            'text-gray-600 dark:text-gray-400' => $result['type_color'] !== 'primary',
                                        ])
                                    />
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $result['title'] }}
                                    </h4>
                                    @if(isset($result['status']))
                                        <x-filament::badge size="sm" color="gray">
                                            {{ $result['status'] }}
                                        </x-filament::badge>
                                    @endif
                                </div>
                                @if($result['subtitle'])
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                        {{ $result['subtitle'] }}
                                    </p>
                                @endif
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    {{ $result['type'] }}
                                </p>
                            </div>
                            <x-filament::icon
                                icon="heroicon-m-chevron-right"
                                class="flex-shrink-0 w-5 h-5 text-gray-400"
                            />
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
