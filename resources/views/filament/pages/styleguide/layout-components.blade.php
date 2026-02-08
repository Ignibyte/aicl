<x-filament-panels::page>
    <div class="space-y-8">

        {{-- SplitLayout --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">SplitLayout</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Two-column layout with configurable ratio. Use for main + sidebar patterns.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Default (2/3 ratio)</h3>
                <x-aicl-split-layout>
                    <x-slot:main>
                        <div class="rounded-lg border-2 border-dashed border-blue-300 bg-blue-50 p-8 text-center text-blue-600 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
                            Main Content (2/3)
                        </div>
                    </x-slot:main>
                    <x-slot:sidebar>
                        <div class="rounded-lg border-2 border-dashed border-purple-300 bg-purple-50 p-8 text-center text-purple-600 dark:border-purple-700 dark:bg-purple-900/20 dark:text-purple-400">
                            Sidebar (1/3)
                        </div>
                    </x-slot:sidebar>
                </x-aicl-split-layout>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">3/4 ratio</h3>
                <x-aicl-split-layout ratio="3/4">
                    <x-slot:main>
                        <div class="rounded-lg border-2 border-dashed border-blue-300 bg-blue-50 p-8 text-center text-blue-600 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
                            Main Content (3/4)
                        </div>
                    </x-slot:main>
                    <x-slot:sidebar>
                        <div class="rounded-lg border-2 border-dashed border-purple-300 bg-purple-50 p-8 text-center text-purple-600 dark:border-purple-700 dark:bg-purple-900/20 dark:text-purple-400">
                            Sidebar (1/4)
                        </div>
                    </x-slot:sidebar>
                </x-aicl-split-layout>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">1/2 ratio (reversed)</h3>
                <x-aicl-split-layout ratio="1/2" :reverse="true">
                    <x-slot:main>
                        <div class="rounded-lg border-2 border-dashed border-blue-300 bg-blue-50 p-8 text-center text-blue-600 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
                            Main Content (1/2)
                        </div>
                    </x-slot:main>
                    <x-slot:sidebar>
                        <div class="rounded-lg border-2 border-dashed border-purple-300 bg-purple-50 p-8 text-center text-purple-600 dark:border-purple-700 dark:bg-purple-900/20 dark:text-purple-400">
                            Sidebar first (1/2)
                        </div>
                    </x-slot:sidebar>
                </x-aicl-split-layout>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- CardGrid --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">CardGrid</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Responsive grid of cards. Scales from 1 column on mobile to N columns on desktop.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">3 Columns (default)</h3>
                <x-aicl-card-grid>
                    @for($i = 1; $i <= 6; $i++)
                        <div class="rounded-lg border border-gray-200 bg-white p-4 text-center text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Card {{ $i }}
                        </div>
                    @endfor
                </x-aicl-card-grid>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">4 Columns</h3>
                <x-aicl-card-grid :cols="4">
                    @for($i = 1; $i <= 8; $i++)
                        <div class="rounded-lg border border-gray-200 bg-white p-4 text-center text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Card {{ $i }}
                        </div>
                    @endfor
                </x-aicl-card-grid>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- StatsRow --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">StatsRow</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Horizontal container for stat cards at the top of dashboards.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <x-aicl-stats-row>
                    <x-aicl-stat-card label="Total Users" value="1,234" icon="heroicon-o-users" trend="up" trend-value="+12%" />
                    <x-aicl-stat-card label="Active Projects" value="42" icon="heroicon-o-briefcase" trend="up" trend-value="+3" />
                    <x-aicl-stat-card label="Tasks Done" value="89%" icon="heroicon-o-check-circle" />
                    <x-aicl-stat-card label="Revenue" value="$45,200" icon="heroicon-o-currency-dollar" trend="down" trend-value="-5%" />
                </x-aicl-stats-row>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- EmptyState --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">EmptyState</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Placeholder shown when a list or section has no data.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">With action</h3>
                <x-aicl-empty-state
                    heading="No projects yet"
                    description="Get started by creating your first project to track work."
                    icon="heroicon-o-briefcase"
                    action-url="#"
                    action-label="Create Project"
                />
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Without action</h3>
                <x-aicl-empty-state
                    heading="No results found"
                    description="Try adjusting your search or filter criteria."
                    icon="heroicon-o-magnifying-glass"
                />
            </div>
        </div>
    </div>
</x-filament-panels::page>
