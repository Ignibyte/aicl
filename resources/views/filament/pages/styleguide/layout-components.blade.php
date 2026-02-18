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

            @php
$splitLayoutCode = <<<'BLADE'
<x-aicl-split-layout ratio="3/4" :reverse="true">
    <x-slot:main>Main Content</x-slot:main>
    <x-slot:sidebar>Sidebar</x-slot:sidebar>
</x-aicl-split-layout>
BLADE;
            @endphp
            <x-aicl-code-block :code="$splitLayoutCode" />
            <x-aicl-component-reference component="split-layout" />
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

            @php
$cardGridCode = <<<'BLADE'
<x-aicl-card-grid :cols="4">
    <div>Card 1</div>
    <div>Card 2</div>
</x-aicl-card-grid>
BLADE;
            @endphp
            <x-aicl-code-block :code="$cardGridCode" />
            <x-aicl-component-reference component="card-grid" />
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

            @php
$statsRowCode = <<<'BLADE'
<x-aicl-stats-row>
    <x-aicl-stat-card label="Total Users" value="1,234" icon="heroicon-o-users" trend="up" trend-value="+12%" />
    <x-aicl-stat-card label="Revenue" value="$45,200" icon="heroicon-o-currency-dollar" />
</x-aicl-stats-row>
BLADE;
            @endphp
            <x-aicl-code-block :code="$statsRowCode" />
            <x-aicl-component-reference component="stats-row" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- EmptyState --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">EmptyState</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Placeholder shown when a list or section has no data.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <x-aicl-empty-state
                    heading="No projects yet"
                    description="Get started by creating your first project to track work."
                    icon="heroicon-o-briefcase"
                    action-url="#"
                    action-label="Create Project"
                />
            </div>

            @php
$emptyStateCode = <<<'BLADE'
<x-aicl-empty-state
    heading="No projects yet"
    description="Get started by creating your first project."
    icon="heroicon-o-briefcase"
    action-url="/projects/create"
    action-label="Create Project"
/>
BLADE;
            @endphp
            <x-aicl-code-block :code="$emptyStateCode" />
            <x-aicl-component-reference component="empty-state" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- IgnibyteLogo --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">IgnibyteLogo</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Brand logo with optional text, supporting multiple sizes and icon-only mode.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Sizes</h3>
                <div class="flex items-end gap-6">
                    @foreach (['sm', 'md', 'lg', 'xl'] as $size)
                        <div class="flex flex-col items-center gap-2">
                            <x-aicl-ignibyte-logo :size="$size" />
                            <span class="text-xs text-gray-500">{{ $size }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Icon Only</h3>
                <div class="flex items-end gap-6">
                    @foreach (['sm', 'md', 'lg'] as $size)
                        <div class="flex flex-col items-center gap-2">
                            <x-aicl-ignibyte-logo :size="$size" :icon-only="true" />
                            <span class="text-xs text-gray-500">{{ $size }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @php
$logoCode = <<<'BLADE'
<x-aicl-ignibyte-logo />
<x-aicl-ignibyte-logo size="lg" />
<x-aicl-ignibyte-logo size="sm" :icon-only="true" />
BLADE;
            @endphp
            <x-aicl-code-block :code="$logoCode" />
            <x-aicl-component-reference component="ignibyte-logo" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- AuthSplitLayout --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">AuthSplitLayout</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">50/50 split layout for authentication pages with branded background. Full-page component shown scaled below.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600" style="height: 300px;">
                    <div style="transform: scale(0.45); transform-origin: top left; width: 222%; height: 222%;">
                        <x-aicl-auth-split-layout>
                            <div class="flex flex-col items-center justify-center p-8">
                                <x-aicl-ignibyte-logo size="md" />
                                <h2 class="mt-6 text-2xl font-bold text-gray-900 dark:text-white">Sign in</h2>
                                <div class="mt-4 w-full max-w-sm space-y-4">
                                    <div class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-400 dark:border-gray-600 dark:bg-gray-800">Email address</div>
                                    <div class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-400 dark:border-gray-600 dark:bg-gray-800">Password</div>
                                    <div class="rounded-lg bg-primary-600 px-4 py-2 text-center text-sm font-medium text-white">Sign in</div>
                                </div>
                            </div>
                        </x-aicl-auth-split-layout>
                    </div>
                </div>
            </div>

            @php
$authSplitCode = <<<'BLADE'
<x-aicl-auth-split-layout
    background-image="/images/auth-bg.jpg"
    overlay-opacity="40"
>
    {{-- Login form content --}}
</x-aicl-auth-split-layout>
BLADE;
            @endphp
            <x-aicl-code-block :code="$authSplitCode" />
            <x-aicl-component-reference component="auth-split-layout" />
        </div>
    </div>
</x-filament-panels::page>
