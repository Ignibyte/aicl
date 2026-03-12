<div wire:poll.30s>
    {{-- View Toggle --}}
    <div class="mb-4 flex items-center gap-4">
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
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Throughput Chart --}}
            <x-filament::section>
                <x-slot name="heading">Throughput (jobs/min)</x-slot>
                <div class="h-48">
                    <div class="flex h-full items-end gap-1">
                        @php
                            $maxThroughput = max(array_column($snapshots, 'throughput')) ?: 1;
                        @endphp
                        @foreach($snapshots as $snapshot)
                            <div class="group relative flex flex-1 flex-col items-center justify-end">
                                <div
                                    class="w-full rounded-t bg-primary-500 dark:bg-primary-400 transition-all duration-200 hover:bg-primary-600 dark:hover:bg-primary-300"
                                    style="height: {{ ($snapshot['throughput'] / $maxThroughput) * 100 }}%"
                                    title="{{ $snapshot['time'] }}: {{ number_format($snapshot['throughput'], 1) }} jobs/min"
                                ></div>
                                @if($loop->index % max(1, intval(count($snapshots) / 6)) === 0)
                                    <span class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">{{ $snapshot['time'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>

            {{-- Runtime Chart --}}
            <x-filament::section>
                <x-slot name="heading">Avg Runtime (ms)</x-slot>
                <div class="h-48">
                    <div class="flex h-full items-end gap-1">
                        @php
                            $maxRuntime = max(array_column($snapshots, 'runtime')) ?: 1;
                        @endphp
                        @foreach($snapshots as $snapshot)
                            <div class="group relative flex flex-1 flex-col items-center justify-end">
                                <div
                                    class="w-full rounded-t bg-yellow-500 dark:bg-yellow-400 transition-all duration-200 hover:bg-yellow-600 dark:hover:bg-yellow-300"
                                    style="height: {{ ($snapshot['runtime'] / $maxRuntime) * 100 }}%"
                                    title="{{ $snapshot['time'] }}: {{ number_format($snapshot['runtime'], 2) }}ms"
                                ></div>
                                @if($loop->index % max(1, intval(count($snapshots) / 6)) === 0)
                                    <span class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">{{ $snapshot['time'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
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
