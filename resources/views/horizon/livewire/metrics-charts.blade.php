<div wire:poll.30s>
    {{-- Loading Overlay --}}
    <div wire:loading.delay class="mb-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Loading metrics...
    </div>

    {{-- View Toggle and Time Range --}}
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <div class="flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
            <button
                wire:click="$set('view', 'queues')"
                @class([
                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                    'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white' => $view === 'queues',
                    'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $view !== 'queues',
                ])
            >
                Queues
            </button>
            <button
                wire:click="$set('view', 'jobs')"
                @class([
                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                    'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white' => $view === 'jobs',
                    'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $view !== 'jobs',
                ])
            >
                Jobs
            </button>
        </div>

        {{-- Time Range Selector --}}
        @if($persistenceEnabled)
            <div class="flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                @foreach(['live' => 'Live', '1h' => '1h', '6h' => '6h', '24h' => '24h', '7d' => '7d', '30d' => '30d'] as $range => $label)
                    <button
                        wire:click="$set('timeRange', '{{ $range }}')"
                        @class([
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white' => $timeRange === $range,
                            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $timeRange !== $range,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Selector --}}
        @if($view === 'queues')
            <select
                wire:model.live="selectedQueue"
                class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                @foreach($measuredQueues as $queue)
                    <option value="{{ $queue }}">{{ $queue }}</option>
                @endforeach
                @if(empty($measuredQueues))
                    <option value="">No queues measured</option>
                @endif
            </select>
        @else
            <select
                wire:model.live="selectedJob"
                class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                <option value="">Select a job...</option>
                @foreach($measuredJobs as $job)
                    <option value="{{ $job }}">{{ class_basename($job) }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if(empty($snapshots))
        <x-aicl-empty-state
            heading="No metrics data available"
            description="Metrics snapshots are collected every 5 minutes. Run aicl:horizon:snapshot to collect now."
            icon="heroicon-o-chart-bar"
        />
    @else
        @php
            $labelInterval = max(1, intval(count($snapshots) / 8));
            $maxThroughput = max(array_column($snapshots, 'throughput')) ?: 1;
            $maxRuntime = max(array_column($snapshots, 'runtime')) ?: 1;
        @endphp
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Throughput Chart --}}
            <x-filament::section>
                <x-slot name="heading">Throughput (jobs/min)</x-slot>
                <div style="height: 180px; display: flex; align-items: flex-end; gap: 1px; margin-bottom: 20px;">
                    @foreach($snapshots as $snapshot)
                        @php $pct = ($snapshot['throughput'] / $maxThroughput) * 100; @endphp
                        <div style="flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; position: relative;" title="{{ $snapshot['time'] }}: {{ number_format($snapshot['throughput'], 1) }} jobs/min">
                            @if($snapshot['throughput'] > 0)
                                <div style="width: 100%; height: {{ max(3, $pct) }}%; border-radius: 3px 3px 0 0; background: rgb(249, 115, 22); min-height: 2px;"></div>
                            @endif
                            @if($loop->index % $labelInterval === 0)
                                <span style="position: absolute; bottom: -18px; font-size: 9px; white-space: nowrap; color: #9ca3af;">{{ $snapshot['time'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            {{-- Runtime Chart --}}
            <x-filament::section>
                <x-slot name="heading">Avg Runtime (ms)</x-slot>
                <div style="height: 180px; display: flex; align-items: flex-end; gap: 1px; margin-bottom: 20px;">
                    @foreach($snapshots as $snapshot)
                        @php $pct = ($snapshot['runtime'] / $maxRuntime) * 100; @endphp
                        <div style="flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; position: relative;" title="{{ $snapshot['time'] }}: {{ number_format($snapshot['runtime'], 2) }}ms">
                            @if($snapshot['runtime'] > 0)
                                <div style="width: 100%; height: {{ max(3, $pct) }}%; border-radius: 3px 3px 0 0; background: rgb(234, 179, 8); min-height: 2px;"></div>
                            @endif
                            @if($loop->index % $labelInterval === 0)
                                <span style="position: absolute; bottom: -18px; font-size: 9px; white-space: nowrap; color: #9ca3af;">{{ $snapshot['time'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        {{-- Summary Stats --}}
        <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
            @php
                $avgThroughput = count($snapshots) > 0 ? array_sum(array_column($snapshots, 'throughput')) / count($snapshots) : 0;
                $avgRuntime = count($snapshots) > 0 ? array_sum(array_column($snapshots, 'runtime')) / count($snapshots) : 0;
                $peakThroughput = max(array_column($snapshots, 'throughput'));
                $peakRuntime = max(array_column($snapshots, 'runtime'));
            @endphp
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($avgThroughput, 1) }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Avg Throughput</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($peakThroughput, 1) }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Peak Throughput</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($avgRuntime, 2) }}ms</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Avg Runtime</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($peakRuntime, 2) }}ms</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Peak Runtime</p>
                </div>
            </x-filament::section>
        </div>
    @endif
</div>
