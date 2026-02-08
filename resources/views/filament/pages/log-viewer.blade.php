<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Log Entries
            @if($this->selectedFile)
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ({{ $this->logEntries->count() }} entries)
                </span>
            @endif
        </x-slot>

        @if(!$this->selectedFile)
            <div class="text-center py-12">
                <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No log files</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No log files found in storage/logs.</p>
            </div>
        @elseif($this->logEntries->isEmpty())
            <div class="text-center py-12">
                <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-green-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No entries</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($this->levelFilter || $this->search)
                        No entries match your current filters.
                    @else
                        This log file is empty.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400 w-48">Timestamp</th>
                            <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400 w-24">Level</th>
                            <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Message</th>
                            <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400 w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->logEntries as $index => $entry)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400 whitespace-nowrap font-mono text-xs">
                                    {{ $entry['timestamp'] }}
                                </td>
                                <td class="py-2 px-3">
                                    <x-filament::badge :color="$this->getLevelColor($entry['level'])">
                                        {{ $entry['level'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="py-2 px-3 text-gray-900 dark:text-white">
                                    <div class="max-w-2xl">
                                        <p class="truncate" title="{{ $entry['message'] }}">
                                            {{ Str::limit($entry['message'], 120) }}
                                        </p>
                                        @if(!empty($entry['context']))
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono truncate">
                                                {{ Str::limit($entry['context'], 80) }}
                                            </p>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2 px-3">
                                    @if(!empty($entry['stack_trace']))
                                        <x-filament::modal width="4xl">
                                            <x-slot name="trigger">
                                                <x-filament::button size="xs" color="gray">
                                                    Stack
                                                </x-filament::button>
                                            </x-slot>

                                            <x-slot name="heading">
                                                Stack Trace
                                            </x-slot>

                                            <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-4 rounded overflow-x-auto max-h-96">{{ $entry['stack_trace'] }}</pre>
                                        </x-filament::modal>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
