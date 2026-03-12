<div wire:poll.10s>
    <div class="fi-ta">
        <div class="fi-ta-content overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                <thead class="divide-y divide-gray-200 dark:divide-white/5">
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6">
                            Name
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            Progress
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            Status
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            Jobs
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:last-of-type:pe-6">
                            Created
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                    @forelse($batches as $batch)
                        <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white sm:first-of-type:ps-6">
                                <div class="font-medium">{{ $batch->name ?? 'Unnamed Batch' }}</div>
                                <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Illuminate\Support\Str::limit($batch->id, 12) }}
                                </div>
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div
                                            @class([
                                                'h-full rounded-full transition-all duration-500',
                                                'bg-green-500' => $batch->progress === 100 && $batch->failed_jobs === 0,
                                                'bg-red-500' => $batch->failed_jobs > 0,
                                                'bg-primary-500' => $batch->progress < 100 && $batch->failed_jobs === 0,
                                            ])
                                            style="width: {{ $batch->progress }}%"
                                        ></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $batch->progress }}%</span>
                                </div>
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm">
                                @if($batch->cancelled_at)
                                    <x-aicl-status-badge label="Cancelled" color="gray" />
                                @elseif($batch->failed_jobs > 0)
                                    <x-aicl-status-badge label="Failed" color="danger" />
                                @elseif($batch->finished_at)
                                    <x-aicl-status-badge label="Finished" color="success" />
                                @elseif($batch->pending_jobs > 0)
                                    <x-aicl-status-badge label="Processing" color="primary" />
                                @else
                                    <x-aicl-status-badge label="Pending" color="warning" />
                                @endif
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $batch->total_jobs }}</span>
                                    <span class="text-xs">total</span>
                                    @if($batch->pending_jobs > 0)
                                        <span class="text-xs text-yellow-600 dark:text-yellow-400">{{ $batch->pending_jobs }} pending</span>
                                    @endif
                                    @if($batch->failed_jobs > 0)
                                        <span class="text-xs text-red-600 dark:text-red-400">{{ $batch->failed_jobs }} failed</span>
                                    @endif
                                </div>
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-500 dark:text-gray-400 sm:last-of-type:pe-6">
                                {{ $batch->created_at_formatted ?? '&mdash;' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4">
                                <x-aicl-empty-state
                                    heading="No job batches found"
                                    description="Job batches will appear here when you use Laravel's Bus::batch() feature."
                                    icon="heroicon-o-rectangle-stack"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
