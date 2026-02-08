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

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Align: start</h3>
                    <x-aicl-action-bar align="start">
                        <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Create New</button>
                        <button class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300">Import</button>
                    </x-aicl-action-bar>
                </div>
            </div>
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
                    <x-aicl-quick-action icon="heroicon-m-star" label="Favorite" />
                    <x-aicl-quick-action icon="heroicon-m-arrow-down-tray" label="Download" href="#" />
                </div>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- AlertBanner --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">AlertBanner</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Dismissible alert with auto-selected icon. Types: info, success, warning, danger.</p>

            <div class="space-y-3">
                <x-aicl-alert-banner type="info">
                    <strong>Info:</strong> This is an informational message. System maintenance is scheduled for tonight.
                </x-aicl-alert-banner>

                <x-aicl-alert-banner type="success">
                    <strong>Success:</strong> Your changes have been saved successfully.
                </x-aicl-alert-banner>

                <x-aicl-alert-banner type="warning">
                    <strong>Warning:</strong> Your storage is almost full. Consider upgrading your plan.
                </x-aicl-alert-banner>

                <x-aicl-alert-banner type="danger">
                    <strong>Danger:</strong> Failed to connect to the database. Please check your configuration.
                </x-aicl-alert-banner>

                <x-aicl-alert-banner type="info" :dismissible="false">
                    <strong>Non-dismissible:</strong> This alert cannot be dismissed by the user.
                </x-aicl-alert-banner>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Divider --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Divider</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Horizontal rule with optional centered label. Use to separate content sections.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-600 dark:text-gray-400">Content above divider</p>
                <x-aicl-divider />
                <p class="text-sm text-gray-600 dark:text-gray-400">Content below simple divider</p>
                <x-aicl-divider label="OR" />
                <p class="text-sm text-gray-600 dark:text-gray-400">Content below labeled divider</p>
                <x-aicl-divider label="Additional Details" />
                <p class="text-sm text-gray-600 dark:text-gray-400">More content here</p>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Spinner --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Spinner</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">SVG loading spinner with configurable size and color. Use in buttons, loading states, and placeholders.</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Sizes</h3>
                    <div class="flex items-center gap-6">
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner size="xs" />
                            <span class="text-xs text-gray-500">xs</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner size="sm" />
                            <span class="text-xs text-gray-500">sm</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner size="md" />
                            <span class="text-xs text-gray-500">md</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner size="lg" />
                            <span class="text-xs text-gray-500">lg</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner size="xl" />
                            <span class="text-xs text-gray-500">xl</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Colors</h3>
                    <div class="flex items-center gap-6">
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner color="primary" />
                            <span class="text-xs text-gray-500">primary</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner color="success" />
                            <span class="text-xs text-gray-500">success</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner color="danger" />
                            <span class="text-xs text-gray-500">danger</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner color="warning" />
                            <span class="text-xs text-gray-500">warning</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <x-aicl-spinner color="gray" />
                            <span class="text-xs text-gray-500">gray</span>
                        </div>
                        <div class="flex flex-col items-center gap-1 rounded bg-primary-600 p-2">
                            <x-aicl-spinner color="white" />
                            <span class="text-xs text-white">white</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">In a Button</h3>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white" disabled>
                        <x-aicl-spinner size="sm" color="white" />
                        Saving...
                    </button>
                </div>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Tabs --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tabs</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Tab container with Alpine.js switching. Auto-discovers child TabPanel components. Variants: underline (default), pills.</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Underline Variant (default)</h3>
                    <x-aicl-tabs default-tab="overview">
                        <x-aicl-tab-panel name="overview" label="Overview">
                            <div class="rounded-lg bg-white p-4 dark:bg-gray-800">
                                <h4 class="font-medium text-gray-900 dark:text-white">Project Overview</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">This is the overview tab content. It renders when the "Overview" tab is active.</p>
                            </div>
                        </x-aicl-tab-panel>
                        <x-aicl-tab-panel name="details" label="Details">
                            <div class="rounded-lg bg-white p-4 dark:bg-gray-800">
                                <h4 class="font-medium text-gray-900 dark:text-white">Project Details</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Detailed information goes here. Each tab panel can contain any content.</p>
                            </div>
                        </x-aicl-tab-panel>
                        <x-aicl-tab-panel name="activity" label="Activity">
                            <div class="rounded-lg bg-white p-4 dark:bg-gray-800">
                                <h4 class="font-medium text-gray-900 dark:text-white">Recent Activity</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Activity log entries would appear here.</p>
                            </div>
                        </x-aicl-tab-panel>
                    </x-aicl-tabs>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Pills Variant</h3>
                    <x-aicl-tabs variant="pills" default-tab="all">
                        <x-aicl-tab-panel name="all" label="All">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Showing all 42 items.</p>
                        </x-aicl-tab-panel>
                        <x-aicl-tab-panel name="active" label="Active">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Showing 28 active items.</p>
                        </x-aicl-tab-panel>
                        <x-aicl-tab-panel name="archived" label="Archived">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Showing 14 archived items.</p>
                        </x-aicl-tab-panel>
                    </x-aicl-tabs>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Auto-selects First Tab</h3>
                    <x-aicl-tabs>
                        <x-aicl-tab-panel name="first" label="First Tab">
                            <p class="text-sm text-gray-600 dark:text-gray-400">This tab is auto-selected because no default-tab was specified.</p>
                        </x-aicl-tab-panel>
                        <x-aicl-tab-panel name="second" label="Second Tab">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Click to see this tab's content.</p>
                        </x-aicl-tab-panel>
                    </x-aicl-tabs>
                </div>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- ActivityFeed --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">ActivityFeed (Livewire)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Real-time activity feed powered by spatie/laravel-activitylog with auto-polling.</p>

            <div class="max-w-2xl">
                <livewire:aicl-activity-feed :per-page="5" :poll-interval="0" heading="Activity Feed Demo" />
            </div>
        </div>
    </div>
</x-filament-panels::page>
