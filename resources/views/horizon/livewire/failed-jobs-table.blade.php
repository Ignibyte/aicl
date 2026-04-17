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
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            Exception
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            Failed At
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                    @forelse($jobs as $job)
                        <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                <div class="max-w-xs truncate font-medium" title="{{ $job->name ?? '' }}">
                                    {{ class_basename($job->name ?? 'Unknown') }}
                                </div>
                                <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $job->id ?? '' }}
                                </div>
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm">
                                <x-aicl-status-badge :label="$job->queue ?? 'default'" color="gray" />
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="max-w-sm truncate" title="{{ $job->exception ?? '' }}">
                                    {{ \Illuminate\Support\Str::limit($job->exception ?? '—', 80) }}
                                </div>
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @if(isset($job->failed_at))
                                    {{ \Carbon\Carbon::createFromTimestamp($job->failed_at)->diffForHumans() }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                <div class="flex items-center gap-2">
                                    <button
                                        wire:click="retry('{{ $job->id }}')"
                                        wire:confirm="Are you sure you want to retry this job?"
                                        wire:loading.attr="disabled"
                                        wire:target="retry('{{ $job->id }}')"
                                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-green-600 transition duration-75 hover:bg-green-50 disabled:opacity-50 dark:text-green-400 dark:hover:bg-green-400/10"
                                        title="Retry"
                                    >
                                        <x-heroicon-m-arrow-path class="h-4 w-4" wire:loading.class="animate-spin" wire:target="retry('{{ $job->id }}')" />
                                        Retry
                                    </button>
                                    <button
                                        wire:click="deleteJob('{{ $job->id }}')"
                                        wire:confirm="Are you sure you want to delete this failed job?"
                                        wire:loading.attr="disabled"
                                        wire:target="deleteJob('{{ $job->id }}')"
                                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-red-600 transition duration-75 hover:bg-red-50 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-400/10"
                                        title="Delete"
                                    >
                                        <x-heroicon-m-trash class="h-4 w-4" />
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4">
                                <x-aicl-empty-state
                                    heading="No failed jobs"
                                    description="All jobs have completed successfully."
                                    icon="heroicon-o-check-circle"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
