<x-filament-panels::page>
    <div class="space-y-8">

        {{-- ActionBar --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">ActionBar</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Horizontal button group container. Nest buttons or QuickAction components inside.</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Align: end (default)</h3>
                    <x-aicl-action-bar>
                        <button class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300">Cancel</button>
                        <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Save</button>
                    </x-aicl-action-bar>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Align: between</h3>
                    <x-aicl-action-bar align="between">
                        <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Delete</button>
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300">Cancel</button>
                            <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Save</button>
                        </div>
                    </x-aicl-action-bar>
                </div>
            </div>

            @php
$actionBarCode = <<<'BLADE'
<x-aicl-action-bar align="between">
    <button>Delete</button>
    <div class="flex gap-2">
        <button>Cancel</button>
        <button>Save</button>
    </div>
</x-aicl-action-bar>
BLADE;
            @endphp
            <x-aicl-code-block :code="$actionBarCode" />
            <x-aicl-component-reference component="action-bar" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- QuickAction --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">QuickAction</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Compact icon button with tooltip. Renders as &lt;a&gt; with href or &lt;button&gt; without.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-2">
                    <x-aicl-quick-action icon="heroicon-m-pencil-square" label="Edit" />
                    <x-aicl-quick-action icon="heroicon-m-trash" label="Delete" />
                    <x-aicl-quick-action icon="heroicon-m-clipboard-document" label="Copy" />
                    <x-aicl-quick-action icon="heroicon-m-share" label="Share" href="#" />
                </div>
            </div>

            @php
$quickActionCode = <<<'BLADE'
<x-aicl-quick-action icon="heroicon-m-pencil-square" label="Edit" />
<x-aicl-quick-action icon="heroicon-m-share" label="Share" href="/share" />
BLADE;
            @endphp
            <x-aicl-code-block :code="$quickActionCode" />
            <x-aicl-component-reference component="quick-action" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- AlertBanner --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">AlertBanner</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Dismissible alert with auto-selected icon. Types: info, success, warning, danger.</p>

            <div class="space-y-3">
                <x-aicl-alert-banner type="info">
                    <strong>Info:</strong> System maintenance is scheduled for tonight.
                </x-aicl-alert-banner>

                <x-aicl-alert-banner type="success">
                    <strong>Success:</strong> Your changes have been saved successfully.
                </x-aicl-alert-banner>

                <x-aicl-alert-banner type="warning">
                    <strong>Warning:</strong> Your storage is almost full.
                </x-aicl-alert-banner>

                <x-aicl-alert-banner type="danger">
                    <strong>Danger:</strong> Failed to connect to the database.
                </x-aicl-alert-banner>
            </div>

            @php
$alertBannerCode = <<<'BLADE'
<x-aicl-alert-banner type="warning">
    <strong>Warning:</strong> Your storage is almost full.
</x-aicl-alert-banner>
BLADE;
            @endphp
            <x-aicl-code-block :code="$alertBannerCode" />
            <x-aicl-component-reference component="alert-banner" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Divider --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Divider</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Horizontal rule with optional centered label.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-600 dark:text-gray-400">Content above</p>
                <x-aicl-divider />
                <p class="text-sm text-gray-600 dark:text-gray-400">Content below</p>
                <x-aicl-divider label="OR" />
                <p class="text-sm text-gray-600 dark:text-gray-400">More content</p>
            </div>

            <x-aicl-code-block code='<x-aicl-divider label="OR" />' />
            <x-aicl-component-reference component="divider" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Spinner --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Spinner</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">SVG loading spinner with configurable size and color.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Sizes</h3>
                <div class="flex items-center gap-6">
                    @foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $size)
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner :size="$size" />
                            <span class="text-xs text-gray-500">{{ $size }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <x-aicl-code-block code='<x-aicl-spinner size="sm" color="primary" />' />
            <x-aicl-component-reference component="spinner" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- CodeBlock --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">CodeBlock</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Code snippet display with show/hide toggle and copy-to-clipboard button.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <x-aicl-code-block code='<x-aicl-stat-card label="Users" value="1,234" icon="heroicon-o-users" />' />
            </div>

            @php
$codeBlockCode = <<<'BLADE'
<x-aicl-code-block :code="$myBladeMarkup" language="blade" />
BLADE;
            @endphp
            <x-aicl-code-block :code="$codeBlockCode" />
            <x-aicl-component-reference component="code-block" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Tabs --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tabs</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Tab container with Alpine.js switching. Variants: underline (default), pills.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <x-aicl-tabs default-tab="overview">
                    <x-aicl-tab-panel name="overview" label="Overview">
                        <div class="rounded-lg bg-white p-4 dark:bg-gray-800">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Overview tab content.</p>
                        </div>
                    </x-aicl-tab-panel>
                    <x-aicl-tab-panel name="details" label="Details">
                        <div class="rounded-lg bg-white p-4 dark:bg-gray-800">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Details tab content.</p>
                        </div>
                    </x-aicl-tab-panel>
                </x-aicl-tabs>
            </div>

            @php
$tabsCode = <<<'BLADE'
<x-aicl-tabs default-tab="overview" variant="pills">
    <x-aicl-tab-panel name="overview" label="Overview">
        Content here
    </x-aicl-tab-panel>
</x-aicl-tabs>
BLADE;
            @endphp
            <x-aicl-code-block :code="$tabsCode" />
            <x-aicl-component-reference component="tabs" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- ActivityFeed --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">ActivityFeed (Livewire)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Real-time activity feed powered by spatie/laravel-activitylog with auto-polling. Requires activity log data.</p>

            <div class="max-w-2xl">
                <livewire:aicl-activity-feed :per-page="5" :poll-interval="0" heading="Activity Feed Demo" />
            </div>

            @php
$activityFeedCode = <<<'BLADE'
<livewire:aicl-activity-feed
    :per-page="10"
    :poll-interval="30"
    heading="Recent Activity"
    subject-type="App\Models\Project"
    :subject-id="$project->id"
/>
BLADE;
            @endphp
            <x-aicl-code-block :code="$activityFeedCode" />
        </div>
    </div>
</x-filament-panels::page>
