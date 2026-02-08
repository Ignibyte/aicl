<x-filament-panels::page>
    <div class="space-y-8">

        {{-- StatCard --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">StatCard</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Displays a label, value, icon, and optional trend indicator. Use for countable relationships and monetary totals.</p>

            <x-aicl-card-grid :cols="4">
                <x-aicl-stat-card label="Total Users" value="1,234" icon="heroicon-o-users" trend="up" trend-value="+12%" />
                <x-aicl-stat-card label="Active Projects" value="42" icon="heroicon-o-briefcase" trend="up" trend-value="+3" />
                <x-aicl-stat-card label="Open Issues" value="17" icon="heroicon-o-exclamation-triangle" trend="down" trend-value="-2" />
                <x-aicl-stat-card label="Revenue" value="$45,200" icon="heroicon-o-currency-dollar" description="Last 30 days" />
            </x-aicl-card-grid>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- KpiCard --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">KpiCard</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Target vs actual with auto-calculated progress bar. Color changes at 80%/50% thresholds.</p>

            <x-aicl-card-grid :cols="3">
                <x-aicl-kpi-card label="Budget Spent" :actual="85000" :target="100000" icon="heroicon-o-currency-dollar" />
                <x-aicl-kpi-card label="Tasks Completed" :actual="60" :target="100" icon="heroicon-o-check-circle" />
                <x-aicl-kpi-card label="Sprint Progress" :actual="25" :target="100" icon="heroicon-o-clock" />
            </x-aicl-card-grid>

            <div class="mt-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                Progress colors: <span class="font-semibold text-green-600">Green (>=80%)</span> |
                <span class="font-semibold text-yellow-600">Yellow (50-79%)</span> |
                <span class="font-semibold text-red-600">Red (&lt;50%)</span>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- TrendCard --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">TrendCard</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Value with SVG sparkline chart. Provide 7-30 data points for the trend line.</p>

            <x-aicl-card-grid :cols="3">
                <x-aicl-trend-card
                    label="Signups"
                    value="342"
                    :data="[12, 15, 18, 22, 19, 25, 30, 28, 35, 42]"
                    description="Last 10 days"
                />
                <x-aicl-trend-card
                    label="Page Views"
                    value="8.2K"
                    :data="[100, 120, 95, 140, 160, 130, 180]"
                    color="success"
                />
                <x-aicl-trend-card
                    label="Error Rate"
                    value="0.3%"
                    :data="[5, 4, 6, 3, 2, 3, 1]"
                    color="danger"
                    description="Trending down"
                />
            </x-aicl-card-grid>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- ProgressCard --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">ProgressCard</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Value with a progress bar. Simpler than KpiCard — use when you only have a percentage.</p>

            <x-aicl-card-grid :cols="3">
                <x-aicl-progress-card label="Storage Used" value="7.2 GB" :progress="72" description="of 10 GB" />
                <x-aicl-progress-card label="Tasks Done" value="45/50" :progress="90" color="success" />
                <x-aicl-progress-card label="Upload Progress" value="23%" :progress="23" color="warning" />
            </x-aicl-card-grid>
        </div>
    </div>
</x-filament-panels::page>
