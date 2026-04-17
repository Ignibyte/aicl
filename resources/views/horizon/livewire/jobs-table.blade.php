<div wire:poll.15s>
    <div class="fi-ta">
        <div class="fi-ta-content overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                <thead class="divide-y divide-gray-200 dark:divide-white/5">
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            Job
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            Queue
                        </th>
                        @if($showStatus)
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            Status
                        </th>
                        @endif
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            Tags
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            Runtime
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                    @forelse($jobs as $job)
                        <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                <div class="max-w-xs truncate font-medium" title="{{ $job->name ?? $job->id ?? '' }}">
                                    {{ class_basename($job->name ?? $job->id ?? 'Unknown') }}
                                </div>
                                <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $job->id ?? '' }}
                                </div>
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm">
                                <x-aicl-status-badge :label="$job->queue ?? 'default'" color="gray" />
                            </td>
                            @if($showStatus)
                            <td class="fi-ta-cell px-3 py-4 text-sm">
                                @php
                                    $status = $job->status ?? 'unknown';
                                    $statusColor = match ($status) {
                                        'completed' => 'success',
                                        'pending' => 'warning',
                                        'reserved' => 'primary',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    };
                                @endphp
                                <x-aicl-status-badge :label="ucfirst($status)" :color="$statusColor" />
                            </td>
                            @endif
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @if(!empty($job->payload->tags ?? []))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach(array_slice($job->payload->tags ?? [], 0, 3) as $tag)
                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                {{ class_basename($tag) }}
                                            </span>
                                        @endforeach
                                        @if(count($job->payload->tags ?? []) > 3)
                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs text-gray-400 dark:text-gray-500">
                                                +{{ count($job->payload->tags) - 3 }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-gray-600">&mdash;</span>
                                @endif
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm font-mono text-gray-500 dark:text-gray-400 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                @if(isset($job->completed_at) && isset($job->reserved_at))
                                    {{ round(($job->completed_at - $job->reserved_at) / 1000, 2) }}s
                                @else
                                    &mdash;
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showStatus ? 5 : 4 }}" class="px-3 py-4">
                                <x-aicl-empty-state
                                    :heading="$emptyMessage"
                                    icon="heroicon-o-queue-list"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
