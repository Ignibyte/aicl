<x-filament-panels::page>
    <div x-data="{ activeTab: @entangle('activeTab') }">
        {{-- Tab Navigation --}}
        <div class="flex gap-x-1 border-b border-gray-200 dark:border-white/10 mb-6">
            <button
                x-on:click="activeTab = 'app-logs'"
                :class="activeTab === 'app-logs'
                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
            >
                Application Logs
            </button>
            <button
                x-on:click="activeTab = 'audit-trail'"
                :class="activeTab === 'audit-trail'
                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
            >
                Audit Trail
            </button>
            <button
                x-on:click="activeTab = 'domain-events'"
                :class="activeTab === 'domain-events'
                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
            >
                Domain Events
            </button>
            <button
                x-on:click="activeTab = 'notifications'"
                :class="activeTab === 'notifications'
                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
            >
                Notifications
            </button>
        </div>

        {{-- Application Logs Tab --}}
        <div x-show="activeTab === 'app-logs'" x-cloak>
            <x-filament::section>
                {{ $this->form }}
            </x-filament::section>

            <div class="mt-6" @if($pollingInterval = $this->getPollingInterval()) wire:poll.{{ $pollingInterval }} @endif>
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            Log Entries
                            @if($this->selectedFile)
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                    ({{ $this->logEntries->count() }} entries)
                                </span>
                            @endif
                            @if($this->liveMode)
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-success-500"></span>
                                    </span>
                                    <span class="text-xs font-medium text-success-600 dark:text-success-400">LIVE</span>
                                </span>
                            @endif
                        </div>
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
            </div>
        </div>

        {{-- Audit Trail Tab --}}
        <div x-show="activeTab === 'audit-trail'" x-cloak>
            @livewire('aicl::audit-table')
        </div>

        {{-- Domain Events Tab --}}
        <div x-show="activeTab === 'domain-events'" x-cloak>
            @livewire('aicl::domain-event-table')
        </div>

        {{-- Notifications Tab --}}
        <div x-show="activeTab === 'notifications'" x-cloak>
            @livewire('aicl::notification-log-table')
        </div>
    </div>
</x-filament-panels::page>
