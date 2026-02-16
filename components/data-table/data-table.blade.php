@props([
    'columns' => [],
    'data' => [],
    'sortable' => true,
    'filterable' => true,
    'paginated' => true,
    'perPage' => 10,
    'perPageOptions' => [5, 10, 25, 50],
    'selectable' => false,
    'emptyMessage' => 'No data available',
    'emptyIcon' => 'heroicon-o-table-cells',
])

<div
    x-data="aiclDataTable({
        columns: @js($columns),
        data: @js($data),
        sortable: @js($sortable),
        filterable: @js($filterable),
        paginated: @js($paginated),
        perPage: @js($perPage),
        perPageOptions: @js($perPageOptions),
        selectable: @js($selectable),
    })"
    {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden']) }}
>
    {{-- Header Bar --}}
    <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
        @if($filterable)
            <div class="relative">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    type="text"
                    x-model.debounce.300ms="search"
                    placeholder="Search..."
                    class="rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                />
            </div>
        @else
            <div></div>
        @endif

        <div class="flex items-center gap-3">
            {{-- Slot for custom header content --}}
            @if(isset($header))
                {{ $header }}
            @endif

            @if($paginated)
                <select
                    x-model.number="pageSize"
                    @change="currentPage = 1"
                    class="rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300"
                >
                    <template x-for="opt in perPageOptions" :key="opt">
                        <option :value="opt" x-text="opt + ' per page'"></option>
                    </template>
                </select>
            @endif
        </div>
    </div>

    {{-- Bulk Actions --}}
    @if($selectable && isset($actions))
        <div x-show="selected.length > 0" x-cloak class="border-b border-gray-200 bg-primary-50 px-4 py-2 dark:border-gray-700 dark:bg-primary-500/10">
            <div class="flex items-center gap-3 text-sm">
                <span class="font-medium text-primary-700 dark:text-primary-400" x-text="selected.length + ' selected'"></span>
                {{ $actions }}
            </div>
        </div>
    @endif

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full" role="grid">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    @if($selectable)
                        <th class="w-10 px-4 py-3">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                @change="toggleAll()"
                                class="rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                aria-label="Select all rows"
                            />
                        </th>
                    @endif
                    <template x-for="col in columns" :key="col.key">
                        <th
                            class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            :style="col.width ? 'width:' + col.width : ''"
                            :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'"
                            role="columnheader"
                            :aria-sort="sortKey === col.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'"
                        >
                            <button
                                x-show="col.sortable !== false && sortable"
                                type="button"
                                @click="sort(col.key)"
                                class="inline-flex items-center gap-1 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:text-gray-200"
                            >
                                <span x-text="col.label"></span>
                                <svg class="h-4 w-4" :class="sortKey === col.key ? 'text-primary-500' : 'text-gray-400'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path x-show="sortKey !== col.key || sortDir === 'asc'" stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75 12 3m0 0 3.75 3.75M12 3v18" />
                                    <path x-show="sortKey === col.key && sortDir === 'desc'" stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25 12 21m0 0-3.75-3.75M12 21V3" />
                                </svg>
                            </button>
                            <span x-show="col.sortable === false || !sortable" x-text="col.label"></span>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                <template x-for="(row, index) in paginatedData" :key="index">
                    <tr
                        class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        @click="$dispatch('row-click', { row, index })"
                    >
                        @if($selectable)
                            <td class="px-4 py-3" @click.stop>
                                <input
                                    type="checkbox"
                                    :checked="selected.includes(index)"
                                    @change="toggleRow(index)"
                                    class="rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                    aria-label="Select row"
                                />
                            </td>
                        @endif
                        <template x-for="col in columns" :key="col.key">
                            <td
                                class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"
                                :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'"
                                x-text="row[col.key]"
                            ></td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- Empty State --}}
    <div x-show="filteredData.length === 0" x-cloak class="px-4 py-12 text-center">
        @if(isset($empty))
            {{ $empty }}
        @else
            <x-filament::icon :icon="$emptyIcon" class="mx-auto mb-3 h-8 w-8 text-gray-400" />
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</p>
        @endif
    </div>

    {{-- Pagination --}}
    @if($paginated)
        <div class="flex items-center justify-between border-t border-gray-200 p-4 dark:border-gray-700" aria-live="polite">
            <span class="text-sm text-gray-500 dark:text-gray-400">
                Showing <span x-text="pageStart"></span>-<span x-text="pageEnd"></span> of <span x-text="filteredData.length"></span>
            </span>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    @click="currentPage = Math.max(1, currentPage - 1)"
                    :disabled="currentPage <= 1"
                    class="rounded-lg px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    Previous
                </button>
                <template x-for="page in totalPages" :key="page">
                    <button
                        type="button"
                        @click="currentPage = page"
                        :class="currentPage === page ? 'bg-primary-500 text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                        class="rounded-lg px-3 py-1.5 text-sm"
                        x-text="page"
                    ></button>
                </template>
                <button
                    type="button"
                    @click="currentPage = Math.min(totalPages, currentPage + 1)"
                    :disabled="currentPage >= totalPages"
                    class="rounded-lg px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    Next
                </button>
            </div>
        </div>
    @endif
</div>
