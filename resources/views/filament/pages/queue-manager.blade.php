<x-filament-panels::page>
    @php
        $horizonAvailable = $this->isHorizonAvailable();
        $queueDriver = $this->getQueueDriver();
        $availableTabs = $this->getAvailableTabs();
    @endphp

    <div x-data="{ activeTab: @entangle('activeTab') }">
        {{-- Tab Navigation --}}
        <div class="flex flex-wrap gap-x-1 gap-y-1 border-b border-gray-200 dark:border-white/10 mb-6">
            @foreach($availableTabs as $tab => $label)
            <button
                x-on:click="activeTab = '{{ $tab }}'"
                :class="activeTab === '{{ $tab }}'
                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
                class="whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors duration-150"
            >
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- Overview Tab --}}
        <div x-show="activeTab === 'overview'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @php
                $stats = $this->getQueueStats();
            @endphp

            {{-- Queue Driver Info --}}
            <div class="mb-6 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                <span>Driver:</span>
                <x-aicl-status-badge :label="ucfirst($queueDriver)" color="gray" />
                @if($horizonAvailable)
                    <x-aicl-status-badge label="Horizon Active" color="success" />
                @else
                    <x-aicl-status-badge label="Horizon Inactive" color="warning" />
                @endif
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                {{-- Pending Jobs --}}
                <x-filament::section>
                    <div class="flex items-center gap-x-3">
                        <div @class([
                            'rounded-lg p-2',
                            'bg-green-50 dark:bg-green-500/10' => $stats['pending'] === 0,
                            'bg-primary-50 dark:bg-primary-500/10' => $stats['pending'] > 0 && $stats['pending'] <= 100,
                            'bg-yellow-50 dark:bg-yellow-500/10' => $stats['pending'] > 100,
                        ])>
                            <x-heroicon-o-clock @class([
                                'h-6 w-6',
                                'text-green-600 dark:text-green-400' => $stats['pending'] === 0,
                                'text-primary-600 dark:text-primary-400' => $stats['pending'] > 0 && $stats['pending'] <= 100,
                                'text-yellow-600 dark:text-yellow-400' => $stats['pending'] > 100,
                            ]) />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Jobs</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['pending']) }}</p>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Failed Jobs --}}
                <x-filament::section>
                    <div class="flex items-center gap-x-3">
                        <div @class([
                            'rounded-lg p-2',
                            'bg-green-50 dark:bg-green-500/10' => $stats['failed'] === 0,
                            'bg-red-50 dark:bg-red-500/10' => $stats['failed'] > 0,
                        ])>
                            @if($stats['failed'] > 0)
                                <x-heroicon-o-exclamation-circle class="h-6 w-6 text-red-600 dark:text-red-400" />
                            @else
                                <x-heroicon-o-check-circle class="h-6 w-6 text-green-600 dark:text-green-400" />
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed Jobs</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['failed']) }}</p>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Jobs Per Minute --}}
                <x-filament::section>
                    <div class="flex items-center gap-x-3">
                        <div class="rounded-lg p-2 bg-primary-50 dark:bg-primary-500/10">
                            <x-heroicon-o-bolt class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Jobs / Min</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['jobs_per_minute'], 1) }}</p>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Total Processes --}}
                <x-filament::section>
                    <div class="flex items-center gap-x-3">
                        <div class="rounded-lg p-2 bg-gray-50 dark:bg-gray-500/10">
                            <x-heroicon-o-cpu-chip class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Processes</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_processes'] }}</p>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            {{-- Last Failure --}}
            <div class="mt-6">
                <x-filament::section>
                    <x-slot name="heading">Last Failure</x-slot>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if($stats['last_failed'])
                            <span class="font-medium text-gray-950 dark:text-white">{{ $stats['last_failed']->job_name }}</span>
                            &mdash; {{ $stats['last_failed']->failed_at->diffForHumans() }}
                        @else
                            No failures recorded.
                        @endif
                    </p>
                </x-filament::section>
            </div>
        </div>

        @if($horizonAvailable)
        {{-- Recent Jobs Tab --}}
        <div x-show="activeTab === 'recent'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @livewire('aicl::horizon-recent-jobs-table')
        </div>

        {{-- Pending Jobs Tab --}}
        <div x-show="activeTab === 'pending'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @livewire('aicl::horizon-pending-jobs-table')
        </div>

        {{-- Completed Jobs Tab --}}
        <div x-show="activeTab === 'completed'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @livewire('aicl::horizon-completed-jobs-table')
        </div>
        @endif

        {{-- Failed Jobs Tab (Filament HasTable) --}}
        <div x-show="activeTab === 'failed-jobs'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            {{ $this->table }}
        </div>

        {{-- Batches Tab (always available — uses job_batches DB table) --}}
        <div x-show="activeTab === 'batches'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @livewire('aicl::horizon-batches-table')
        </div>

        @if($horizonAvailable)
        {{-- Metrics Tab --}}
        <div x-show="activeTab === 'metrics'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @livewire('aicl::horizon-metrics-charts')
        </div>

        {{-- Workload Tab --}}
        <div x-show="activeTab === 'workload'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @php
                $workload = $stats['workload'] ?? [];
            @endphp

            @if(empty($workload))
                <x-aicl-empty-state
                    heading="No workload data available"
                    description="Horizon may not be running. Start it with the aicl:horizon command."
                    icon="heroicon-o-server-stack"
                />
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($workload as $queue)
                        <x-filament::section>
                            <x-slot name="heading">{{ $queue['name'] ?? 'Unknown' }}</x-slot>
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($queue['length'] ?? 0) }}</p>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Jobs</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $queue['wait'] ?? 0 }}s</p>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Wait</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $queue['processes'] ?? 0 }}</p>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Workers</p>
                                </div>
                            </div>
                        </x-filament::section>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Supervisors Tab --}}
        <div x-show="activeTab === 'supervisors'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @php
                $supervisors = $this->getSupervisors();
            @endphp

            @if(empty($supervisors))
                <x-aicl-empty-state
                    heading="No supervisors running"
                    description="Start Horizon with the aicl:horizon command."
                    icon="heroicon-o-server"
                />
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach($supervisors as $supervisor)
                        <x-filament::section>
                            <x-slot name="heading">
                                <div class="flex items-center gap-2">
                                    {{ $supervisor->name ?? 'Unknown' }}
                                    <x-aicl-status-badge
                                        :label="ucfirst($supervisor->status ?? 'unknown')"
                                        :color="match($supervisor->status ?? '') { 'running' => 'success', 'paused' => 'warning', default => 'gray' }"
                                    />
                                </div>
                            </x-slot>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">PID</span>
                                    <span class="font-mono text-gray-950 dark:text-white">{{ $supervisor->pid ?? '&mdash;' }}</span>
                                </div>
                                @if(!empty($supervisor->processes))
                                    <div class="pt-2 border-t border-gray-100 dark:border-white/10">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Processes</p>
                                        @foreach((array) $supervisor->processes as $queue => $count)
                                            <div class="flex justify-between">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $queue }}</span>
                                                <span class="font-medium text-gray-950 dark:text-white">{{ $count }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </x-filament::section>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Monitoring Tab --}}
        <div x-show="activeTab === 'monitoring'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @livewire('aicl::horizon-monitored-tags-table')
        </div>
        @endif
    </div>
</x-filament-panels::page>
