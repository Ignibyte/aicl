<x-filament-panels::page>
    <div class="space-y-8">

        {{-- MetadataList --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">MetadataList</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Key-value definition list. Use in detail views and sidebars for entity attributes.</p>

            <div class="max-w-lg">
                <x-aicl-metadata-list :items="[
                    'Project Name' => 'AICL Framework',
                    'Status' => 'Active',
                    'Owner' => 'Admin User',
                    'Created' => 'January 15, 2026',
                    'Last Updated' => '2 hours ago',
                    'Priority' => 'High',
                ]" />
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- InfoCard --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">InfoCard</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Card wrapper with heading and key-value content. Combines a card with MetadataList-style data.</p>

            <x-aicl-card-grid :cols="2">
                <x-aicl-info-card heading="Project Details" :items="[
                    'Name' => 'AICL Framework',
                    'Status' => 'Active',
                    'Priority' => 'High',
                    'Budget' => '$50,000',
                ]" />
                <x-aicl-info-card heading="Schedule" icon="heroicon-o-calendar" :items="[
                    'Start Date' => 'Jan 1, 2026',
                    'End Date' => 'Dec 31, 2026',
                    'Duration' => '12 months',
                    'Sprint' => 'Sprint 5',
                ]" />
            </x-aicl-card-grid>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- StatusBadge --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">StatusBadge</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Colored badge for status, state, or category values. Matches Filament's design tokens.</p>

            <div class="flex flex-wrap gap-3">
                <x-aicl-status-badge label="Primary" color="primary" />
                <x-aicl-status-badge label="Active" color="success" />
                <x-aicl-status-badge label="Pending" color="warning" />
                <x-aicl-status-badge label="Failed" color="danger" />
                <x-aicl-status-badge label="Info" color="info" />
                <x-aicl-status-badge label="Default" color="gray" />
            </div>

            <div class="mt-3">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Color aliases</h3>
                <div class="flex flex-wrap gap-3">
                    <x-aicl-status-badge label="Green" color="green" />
                    <x-aicl-status-badge label="Yellow" color="yellow" />
                    <x-aicl-status-badge label="Red" color="red" />
                    <x-aicl-status-badge label="Blue" color="blue" />
                </div>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Timeline --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Timeline</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Vertical timeline with colored dots. Use for audit logs, activity history, and entity lifecycle.</p>

            <div class="max-w-2xl">
                <x-aicl-timeline :entries="[
                    ['date' => 'Feb 5, 2026 — 2:30 PM', 'title' => 'Project archived', 'description' => 'Moved to archive by Admin', 'color' => 'gray'],
                    ['date' => 'Feb 1, 2026 — 10:00 AM', 'title' => 'Project completed', 'description' => 'All tasks finished', 'color' => 'green'],
                    ['date' => 'Jan 20, 2026 — 3:15 PM', 'title' => 'Status changed', 'description' => 'Draft → Active', 'color' => 'blue'],
                    ['date' => 'Jan 15, 2026 — 9:00 AM', 'title' => 'Project created', 'description' => 'Created by Admin User', 'color' => 'green'],
                ]" />
            </div>
        </div>
    </div>
</x-filament-panels::page>
