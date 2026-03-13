<x-filament-panels::page>
    @php
        $horizonAvailable = $this->isHorizonAvailable();
        $queueDriver = $this->getQueueDriver();
    @endphp

    <div x-data="{ activeSection: @entangle('activeSection').live, activeTab: @entangle('activeTab').live }">
        {{-- Section Navigation --}}
        <div class="mb-6 flex flex-wrap gap-2">
            <button
                x-on:click="activeSection = 'queues'; activeTab = 'overview'"
                :class="activeSection === 'queues'
                    ? 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/30'
                    : 'bg-gray-50 text-gray-600 ring-gray-500/10 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10 dark:hover:bg-white/10'"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold ring-1 ring-inset transition-colors"
            >
                <x-heroicon-o-queue-list class="h-4 w-4" />
                Queues & Jobs
            </button>
            <button
                x-on:click="activeSection = 'scheduler'; activeTab = 'registered'"
                :class="activeSection === 'scheduler'
                    ? 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/30'
                    : 'bg-gray-50 text-gray-600 ring-gray-500/10 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10 dark:hover:bg-white/10'"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold ring-1 ring-inset transition-colors"
            >
                <x-heroicon-o-calendar class="h-4 w-4" />
                Scheduler
            </button>
            <button
                x-on:click="activeSection = 'notifications'; activeTab = 'delivery-health'"
                :class="activeSection === 'notifications'
                    ? 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/30'
                    : 'bg-gray-50 text-gray-600 ring-gray-500/10 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10 dark:hover:bg-white/10'"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold ring-1 ring-inset transition-colors"
            >
                <x-heroicon-o-bell-alert class="h-4 w-4" />
                Notifications
            </button>
            <button
                x-on:click="activeSection = 'sessions'; activeTab = 'sessions'"
                :class="activeSection === 'sessions'
                    ? 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/30'
                    : 'bg-gray-50 text-gray-600 ring-gray-500/10 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10 dark:hover:bg-white/10'"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold ring-1 ring-inset transition-colors"
            >
                <x-heroicon-o-users class="h-4 w-4" />
                Sessions
            </button>
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- QUEUES & JOBS SECTION                                       --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div x-show="activeSection === 'queues'" x-cloak>
            {{-- Tab Navigation --}}
            @php $queueTabs = $this->getQueueTabs(); @endphp
            <div class="flex flex-wrap gap-x-1 gap-y-1 border-b border-gray-200 dark:border-white/10 mb-6">
                @foreach($queueTabs as $tab => $label)
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

            {{-- Batches Tab --}}
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

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- SCHEDULER SECTION                                           --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div x-show="activeSection === 'scheduler'" x-cloak>
            {{-- Tab Navigation --}}
            @php $schedulerTabs = $this->getSchedulerTabs(); @endphp
            <div class="flex flex-wrap gap-x-1 gap-y-1 border-b border-gray-200 dark:border-white/10 mb-6">
                @foreach($schedulerTabs as $tab => $label)
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

            {{-- Registered Tasks Tab --}}
            <div x-show="activeTab === 'registered'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                @php
                    $schedulerStats = $this->getSchedulerStats();
                    $registeredTasks = $this->getRegisteredTasks();
                @endphp

                {{-- Scheduler Stats --}}
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4 mb-6">
                    <x-filament::section>
                        <div class="flex items-center gap-x-3">
                            <div class="rounded-lg p-2 bg-primary-50 dark:bg-primary-500/10">
                                <x-heroicon-o-calendar class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Registered</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $schedulerStats['total_registered'] }}</p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="flex items-center gap-x-3">
                            <div class="rounded-lg p-2 bg-gray-50 dark:bg-gray-500/10">
                                <x-heroicon-o-clock class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Run</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $schedulerStats['last_run_at'] ?? 'Never' }}</p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="flex items-center gap-x-3">
                            <div @class([
                                'rounded-lg p-2',
                                'bg-green-50 dark:bg-green-500/10' => $schedulerStats['failed_24h'] === 0,
                                'bg-red-50 dark:bg-red-500/10' => $schedulerStats['failed_24h'] > 0,
                            ])>
                                @if($schedulerStats['failed_24h'] > 0)
                                    <x-heroicon-o-exclamation-circle class="h-6 w-6 text-red-600 dark:text-red-400" />
                                @else
                                    <x-heroicon-o-check-circle class="h-6 w-6 text-green-600 dark:text-green-400" />
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed (24h)</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $schedulerStats['failed_24h'] }}</p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="flex items-center gap-x-3">
                            <div @class([
                                'rounded-lg p-2',
                                'bg-green-50 dark:bg-green-500/10' => $schedulerStats['success_rate_24h'] >= 95,
                                'bg-yellow-50 dark:bg-yellow-500/10' => $schedulerStats['success_rate_24h'] >= 80 && $schedulerStats['success_rate_24h'] < 95,
                                'bg-red-50 dark:bg-red-500/10' => $schedulerStats['success_rate_24h'] < 80,
                            ])>
                                <x-heroicon-o-chart-bar @class([
                                    'h-6 w-6',
                                    'text-green-600 dark:text-green-400' => $schedulerStats['success_rate_24h'] >= 95,
                                    'text-yellow-600 dark:text-yellow-400' => $schedulerStats['success_rate_24h'] >= 80 && $schedulerStats['success_rate_24h'] < 95,
                                    'text-red-600 dark:text-red-400' => $schedulerStats['success_rate_24h'] < 80,
                                ]) />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Success Rate</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $schedulerStats['success_rate_24h'] }}%</p>
                            </div>
                        </div>
                    </x-filament::section>
                </div>

                {{-- Registered Tasks Table --}}
                @if(empty($registeredTasks))
                    <x-aicl-empty-state
                        heading="No registered tasks"
                        description="No scheduled tasks are registered. Add tasks in routes/console.php."
                        icon="heroicon-o-calendar"
                    />
                @else
                    <x-filament::section>
                        <x-slot name="heading">Registered Tasks</x-slot>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-white/10">
                                        <th class="pb-3 pr-6 text-left font-medium text-gray-500 dark:text-gray-400">Command</th>
                                        <th class="pb-3 pr-6 text-left font-medium text-gray-500 dark:text-gray-400">Schedule</th>
                                        <th class="pb-3 pr-6 text-left font-medium text-gray-500 dark:text-gray-400">Next Due</th>
                                        <th class="pb-3 pr-6 text-left font-medium text-gray-500 dark:text-gray-400">Last Status</th>
                                        <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Last Run</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach($registeredTasks as $task)
                                        <tr>
                                            <td class="py-3 pr-6 font-mono text-gray-950 dark:text-white">{{ $task['command'] }}</td>
                                            <td class="py-3 pr-6 font-mono text-gray-500 dark:text-gray-400">{{ $task['expression'] }}</td>
                                            <td class="py-3 pr-6 text-gray-500 dark:text-gray-400">{{ $task['next_due'] ?? '—' }}</td>
                                            <td class="py-3 pr-6">
                                                @if($task['last_status'])
                                                    <x-aicl-status-badge
                                                        :label="ucfirst($task['last_status'])"
                                                        :color="match($task['last_status']) { 'success' => 'success', 'failed' => 'danger', 'running' => 'info', default => 'gray' }"
                                                    />
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                                @endif
                                            </td>
                                            <td class="py-3 text-gray-500 dark:text-gray-400">{{ $task['last_run'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-filament::section>
                @endif
            </div>

            {{-- Execution History Tab --}}
            <div x-show="activeTab === 'history'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                @livewire('aicl::schedule-history-table')
            </div>

            {{-- Failures Tab --}}
            <div x-show="activeTab === 'schedule-failures'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                @livewire('aicl::schedule-history-table', ['failedOnly' => true])
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- NOTIFICATIONS SECTION                                       --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div x-show="activeSection === 'notifications'" x-cloak>
            {{-- Tab Navigation --}}
            @php $notificationTabs = $this->getNotificationTabs(); @endphp
            <div class="flex flex-wrap gap-x-1 gap-y-1 border-b border-gray-200 dark:border-white/10 mb-6">
                @foreach($notificationTabs as $tab => $label)
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

            {{-- Delivery Health Tab --}}
            <div x-show="activeTab === 'delivery-health'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                @php
                    $deliveryHealth = $this->getNotificationDeliveryHealth();
                    $queueDepth = $this->getNotificationQueueDepth();
                    $stuckDeliveries = $this->getStuckDeliveries();
                @endphp

                {{-- Summary Stats --}}
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3 mb-6">
                    <x-filament::section>
                        <div class="flex items-center gap-x-3">
                            <div @class([
                                'rounded-lg p-2',
                                'bg-green-50 dark:bg-green-500/10' => $queueDepth === 0,
                                'bg-primary-50 dark:bg-primary-500/10' => $queueDepth > 0 && $queueDepth <= 50,
                                'bg-yellow-50 dark:bg-yellow-500/10' => $queueDepth > 50,
                            ])>
                                <x-heroicon-o-inbox-stack @class([
                                    'h-6 w-6',
                                    'text-green-600 dark:text-green-400' => $queueDepth === 0,
                                    'text-primary-600 dark:text-primary-400' => $queueDepth > 0 && $queueDepth <= 50,
                                    'text-yellow-600 dark:text-yellow-400' => $queueDepth > 50,
                                ]) />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Queue Depth</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($queueDepth) }}</p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="flex items-center gap-x-3">
                            <div @class([
                                'rounded-lg p-2',
                                'bg-green-50 dark:bg-green-500/10' => $stuckDeliveries === 0,
                                'bg-red-50 dark:bg-red-500/10' => $stuckDeliveries > 0,
                            ])>
                                @if($stuckDeliveries > 0)
                                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-red-600 dark:text-red-400" />
                                @else
                                    <x-heroicon-o-check-circle class="h-6 w-6 text-green-600 dark:text-green-400" />
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Stuck Deliveries</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stuckDeliveries }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">Pending &gt; 30 min</p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="flex items-center gap-x-3">
                            <div class="rounded-lg p-2 bg-primary-50 dark:bg-primary-500/10">
                                <x-heroicon-o-signal class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Channels</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ count($deliveryHealth) }}</p>
                            </div>
                        </div>
                    </x-filament::section>
                </div>

                {{-- Per-Channel Delivery Health --}}
                @if(empty($deliveryHealth))
                    <x-aicl-empty-state
                        heading="No notification channels configured"
                        description="Add notification channels in the admin to start tracking delivery health."
                        icon="heroicon-o-bell"
                    />
                @else
                    <x-filament::section>
                        <x-slot name="heading">Channel Delivery Health (24h)</x-slot>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-white/10">
                                        <th class="pb-3 pr-6 text-left font-medium text-gray-500 dark:text-gray-400">Channel</th>
                                        <th class="pb-3 pr-6 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
                                        <th class="pb-3 pr-6 text-right font-medium text-gray-500 dark:text-gray-400">Total</th>
                                        <th class="pb-3 pr-6 text-right font-medium text-gray-500 dark:text-gray-400">Delivered</th>
                                        <th class="pb-3 pr-6 text-right font-medium text-gray-500 dark:text-gray-400">Failed</th>
                                        <th class="pb-3 pr-6 text-right font-medium text-gray-500 dark:text-gray-400">Pending</th>
                                        <th class="pb-3 text-right font-medium text-gray-500 dark:text-gray-400">Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach($deliveryHealth as $channel)
                                        <tr>
                                            <td class="py-3 pr-6 font-medium text-gray-950 dark:text-white">{{ $channel['channel_name'] }}</td>
                                            <td class="py-3 pr-6">
                                                <x-aicl-status-badge :label="$channel['channel_type']" color="gray" />
                                            </td>
                                            <td class="py-3 pr-6 text-right text-gray-950 dark:text-white">{{ $channel['total'] }}</td>
                                            <td class="py-3 pr-6 text-right text-green-600 dark:text-green-400">{{ $channel['delivered'] }}</td>
                                            <td class="py-3 pr-6 text-right {{ $channel['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $channel['failed'] }}</td>
                                            <td class="py-3 pr-6 text-right text-gray-500 dark:text-gray-400">{{ $channel['pending'] }}</td>
                                            <td class="py-3 text-right">
                                                <span @class([
                                                    'font-semibold',
                                                    'text-green-600 dark:text-green-400' => $channel['success_rate'] >= 95,
                                                    'text-yellow-600 dark:text-yellow-400' => $channel['success_rate'] >= 80 && $channel['success_rate'] < 95,
                                                    'text-red-600 dark:text-red-400' => $channel['success_rate'] < 80,
                                                ])>{{ $channel['success_rate'] }}%</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-filament::section>
                @endif
            </div>

            {{-- Failed Deliveries Tab --}}
            <div x-show="activeTab === 'failed-deliveries'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                @livewire('aicl::failed-deliveries-table')
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- SESSIONS SECTION                                            --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div x-show="activeSection === 'sessions'" x-cloak>
            @php
                $sessions = $this->getActiveSessions();
                $currentSessionId = request()->session()->getId();
                $isSuperAdmin = auth()->user()?->hasRole('super_admin') ?? false;
            @endphp

            <div wire:poll.5s>
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-filament::icon
                                icon="heroicon-o-users"
                                class="h-5 w-5"
                            />
                            <span>Connected Sessions</span>
                        </div>
                    </x-slot>

                    <x-slot name="afterHeader">
                        <x-filament::badge color="info">
                            {{ $sessions->count() }} active
                        </x-filament::badge>
                    </x-slot>

                    @if ($sessions->isEmpty())
                        <div class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">
                            No active sessions tracked yet. Sessions appear after user activity.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">User</th>
                                        <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Session ID</th>
                                        <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Current Page</th>
                                        <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Last Seen</th>
                                        @if ($isSuperAdmin)
                                            <th class="text-right py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sessions as $session)
                                        @php
                                            $isCurrentSession = ($session['session_id'] ?? '') === $currentSessionId;
                                            $lastSeen = \Carbon\Carbon::parse($session['last_seen_at'] ?? now());
                                            $pagePath = parse_url($session['current_url'] ?? '', PHP_URL_PATH) ?: '—';
                                        @endphp
                                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                                            <td class="py-3 px-4">
                                                <div class="flex items-center gap-2">
                                                    <span class="relative flex h-2.5 w-2.5">
                                                        @if ($isCurrentSession || $lastSeen->diffInMinutes(now()) < 2)
                                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                                                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-success-500"></span>
                                                        @else
                                                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-warning-500"></span>
                                                        @endif
                                                    </span>
                                                    <div>
                                                        <span class="font-medium text-gray-900 dark:text-white">
                                                            {{ $session['user_name'] ?? 'Unknown' }}
                                                            @if ($isCurrentSession)
                                                                <span class="text-xs text-gray-400">(you)</span>
                                                            @endif
                                                        </span>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $session['user_email'] ?? '' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono text-gray-700 dark:text-gray-300">
                                                    {{ $session['session_id_short'] ?? '—' }}
                                                </code>
                                            </td>
                                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">
                                                {{ $pagePath }}
                                            </td>
                                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">
                                                @if ($isCurrentSession)
                                                    Now
                                                @else
                                                    {{ $lastSeen->diffForHumans(short: true) }}
                                                @endif
                                            </td>
                                            @if ($isSuperAdmin)
                                                <td class="py-3 px-4 text-right">
                                                    @if (! $isCurrentSession)
                                                        <x-filament::button
                                                            color="danger"
                                                            size="xs"
                                                            icon="heroicon-o-x-mark"
                                                            wire:click="terminateSession('{{ $session['session_id'] }}')"
                                                            wire:confirm="Are you sure you want to terminate the session for {{ $session['user_name'] ?? 'this user' }}? They will be logged out immediately."
                                                        >
                                                            Kill
                                                        </x-filament::button>
                                                    @else
                                                        <span class="text-xs text-gray-400">—</span>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
