<div wire:poll.10s>
    {{-- Add Tag Form --}}
    <div class="mb-4 flex items-center gap-3">
        <input
            wire:model="newTag"
            wire:keydown.enter="monitor"
            type="text"
            placeholder="Enter a tag to monitor (e.g., App\Models\User:42)"
            class="fi-input block w-full rounded-lg border-none bg-white py-1.5 pe-3 ps-3 text-base text-gray-950 shadow-sm outline-none ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
        />
        <button
            wire:click="monitor"
            wire:loading.attr="disabled"
            wire:target="monitor"
            class="fi-btn inline-flex items-center justify-center gap-1 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition duration-75 hover:bg-primary-500 disabled:opacity-50 dark:bg-primary-500 dark:hover:bg-primary-400"
        >
            <x-heroicon-m-plus class="h-4 w-4" wire:loading.class="animate-spin" wire:target="monitor" />
            Monitor
        </button>
    </div>

    {{-- Monitored Tags --}}
    <div class="fi-ta">
        <div class="fi-ta-content overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                <thead class="divide-y divide-gray-200 dark:divide-white/5">
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            Tag
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-end text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                    @forelse($tags as $tag)
                        <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="fi-ta-cell px-3 py-4 text-sm font-medium text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                <span class="font-mono">{{ $tag }}</span>
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-end sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                <button
                                    wire:click="stopMonitoring('{{ $tag }}')"
                                    wire:confirm="Stop monitoring this tag?"
                                    wire:loading.attr="disabled"
                                    wire:target="stopMonitoring('{{ $tag }}')"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-red-600 transition duration-75 hover:bg-red-50 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-400/10"
                                >
                                    <x-heroicon-m-x-mark class="h-4 w-4" />
                                    Stop
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-3 py-4">
                                <x-aicl-empty-state
                                    heading="No tags being monitored"
                                    description="Add a tag above to start monitoring."
                                    icon="heroicon-o-tag"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
