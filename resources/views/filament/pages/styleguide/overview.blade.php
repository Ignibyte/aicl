<x-filament-panels::page>
    <div class="space-y-8">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">AICL Component Library</h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                A curated set of Blade components designed for AI-generated dashboard interfaces.
                Each component includes AI decision rules that guide code generation.
            </p>
        </div>

        <x-aicl-card-grid cols="2">
            {{-- Layout Components --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                        <x-filament::icon icon="heroicon-o-rectangle-group" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Layout Components</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">4 components</p>
                    </div>
                </div>
                <ul class="mt-4 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>SplitLayout — Two-column with configurable ratio</li>
                    <li>CardGrid — Responsive grid container</li>
                    <li>StatsRow — Horizontal stat card row</li>
                    <li>EmptyState — No-data placeholder with action</li>
                </ul>
            </div>

            {{-- Metric Components --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                        <x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Metric Components</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">4 components</p>
                    </div>
                </div>
                <ul class="mt-4 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>StatCard — Value with trend indicator</li>
                    <li>KpiCard — Target vs actual with progress</li>
                    <li>TrendCard — Value with sparkline chart</li>
                    <li>ProgressCard — Value with progress bar</li>
                </ul>
            </div>

            {{-- Data Display Components --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
                        <x-filament::icon icon="heroicon-o-table-cells" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Data Display Components</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">4 components</p>
                    </div>
                </div>
                <ul class="mt-4 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>MetadataList — Key-value definition list</li>
                    <li>InfoCard — Card with metadata content</li>
                    <li>StatusBadge — Colored status indicator</li>
                    <li>Timeline — Vertical event timeline</li>
                </ul>
            </div>

            {{-- Action Components --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                        <x-filament::icon icon="heroicon-o-cursor-arrow-rays" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Action & Utility Components</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">9 components</p>
                    </div>
                </div>
                <ul class="mt-4 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>ActionBar — Button group container</li>
                    <li>QuickAction — Icon button with tooltip</li>
                    <li>AlertBanner — Dismissible alert message</li>
                    <li>Divider — Horizontal rule with label</li>
                    <li>ActivityFeed — Real-time Livewire feed</li>
                    <li>Spinner — SVG loading spinner</li>
                    <li>AuthSplitLayout — 50/50 split auth page</li>
                    <li>Tabs — Tab container with Alpine.js switching</li>
                    <li>TabPanel — Individual tab content panel</li>
                </ul>
            </div>
        </x-aicl-card-grid>
    </div>
</x-filament-panels::page>
