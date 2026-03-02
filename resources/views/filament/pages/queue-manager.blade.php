<x-filament-panels::page>
    <div x-data="{ activeTab: @entangle('activeTab') }">
        {{-- Tab Navigation --}}
        <div class="flex gap-x-1 border-b border-gray-200 dark:border-white/10 mb-6">
            <button
                x-on:click="activeTab = 'overview'"
                :class="activeTab === 'overview'
                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
            >
                Overview
            </button>
            <button
                x-on:click="activeTab = 'queued-jobs'"
                :class="activeTab === 'queued-jobs'
                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
            >
                Queued Jobs
            </button>
            <button
                x-on:click="activeTab = 'failed-jobs'"
                :class="activeTab === 'failed-jobs'
                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
            >
                Failed Jobs
            </button>
        </div>

        {{-- Overview Tab --}}
        <div x-show="activeTab === 'overview'" x-cloak>
            @php
                $stats = $this->getQueueStats();
                $totalPending = $stats['pending'];
                $failedCount = $stats['failed'];
                $lastFailed = $stats['last_failed'];
            @endphp

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                {{-- Pending Jobs --}}
                <x-filament::section>
                    <div class="flex items-center gap-x-3">
                        <div @class([
                            'rounded-lg p-2',
                            'bg-success-50 dark:bg-success-500/10' => $totalPending === 0,
                            'bg-primary-50 dark:bg-primary-500/10' => $totalPending > 0 && $totalPending <= 100,
                            'bg-warning-50 dark:bg-warning-500/10' => $totalPending > 100,
                        ])>
                            @if($totalPending > 100)
                                <x-heroicon-o-exclamation-triangle @class([
                                    'h-6 w-6',
                                    'text-warning-600 dark:text-warning-400',
                                ]) />
                            @else
                                <x-heroicon-o-clock @class([
                                    'h-6 w-6',
                                    'text-success-600 dark:text-success-400' => $totalPending === 0,
                                    'text-primary-600 dark:text-primary-400' => $totalPending > 0,
                                ]) />
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Jobs</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalPending) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if($stats['pending_high'] > 0)
                                    {{ $stats['pending_high'] }} high priority
                                @else
                                    Queue is processing
                                @endif
                            </p>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Failed Jobs --}}
                <x-filament::section>
                    <div class="flex items-center gap-x-3">
                        <div @class([
                            'rounded-lg p-2',
                            'bg-success-50 dark:bg-success-500/10' => $failedCount === 0,
                            'bg-danger-50 dark:bg-danger-500/10' => $failedCount > 0,
                        ])>
                            @if($failedCount > 0)
                                <x-heroicon-o-exclamation-circle class="h-6 w-6 text-danger-600 dark:text-danger-400" />
                            @else
                                <x-heroicon-o-check-circle class="h-6 w-6 text-success-600 dark:text-success-400" />
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed Jobs</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($failedCount) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if($failedCount > 0)
                                    Requires attention
                                @else
                                    All jobs successful
                                @endif
                            </p>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Last Failure --}}
                <x-filament::section>
                    <div class="flex items-center gap-x-3">
                        <div @class([
                            'rounded-lg p-2',
                            'bg-warning-50 dark:bg-warning-500/10' => $lastFailed && $lastFailed->failed_at->isToday(),
                            'bg-gray-50 dark:bg-gray-500/10' => !$lastFailed || !$lastFailed->failed_at->isToday(),
                        ])>
                            <x-heroicon-o-clock @class([
                                'h-6 w-6',
                                'text-warning-600 dark:text-warning-400' => $lastFailed && $lastFailed->failed_at->isToday(),
                                'text-gray-400 dark:text-gray-500' => !$lastFailed || !$lastFailed->failed_at->isToday(),
                            ]) />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Failure</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ $lastFailed?->failed_at?->diffForHumans() ?? 'Never' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $lastFailed?->job_name ?? 'No failures recorded' }}
                            </p>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <div class="mt-6">
                <x-filament::section>
                    <x-slot name="heading">
                        Queue Overview
                    </x-slot>
                    <x-slot name="description">
                        Monitor your application's job queues and manage failed jobs.
                    </x-slot>

                    <div class="prose dark:prose-invert max-w-none">
                        <p>
                            This dashboard provides an overview of your application's queue system.
                            You can see pending jobs, failed jobs, and retry or delete failed jobs as needed.
                        </p>
                        <ul>
                            <li><strong>Pending Jobs:</strong> Jobs waiting to be processed across all queues.</li>
                            <li><strong>Failed Jobs:</strong> Jobs that have failed and may need attention.</li>
                            <li><strong>Last Failure:</strong> When the most recent job failure occurred.</li>
                        </ul>
                    </div>
                </x-filament::section>
            </div>
        </div>

        {{-- Queued Jobs Tab --}}
        <div x-show="activeTab === 'queued-jobs'" x-cloak>
            @livewire('aicl::queued-jobs-table')
        </div>

        {{-- Failed Jobs Tab --}}
        <div x-show="activeTab === 'failed-jobs'" x-cloak>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
